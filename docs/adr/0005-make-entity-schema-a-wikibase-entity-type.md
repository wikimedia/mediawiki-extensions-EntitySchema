# 0005: Make EntitySchema a Wikibase Entity type

## Status

Rejected (2023-10-16)

## Context

We made a previous decision on this in January/February 2023 which is documented in this repository's first ADR: [0001 Extend Entity Schema to support additional “traits”](./0001-extend-entity-schema-codebase.md).
Back then, the decision was made on the core premise that Product is agnostic as to whether EntitySchema will functionally be a type of "Wikibase Entity".
However, this premise was always untrue, which became clear as development progressed:

EntitySchemas have been given a canonical URI with the same scheme as other Wikibase Entities, like http://www.wikidata.org/entity/E123,
and we are also required to return them in the Action API with the datavalue-type of `wikibase-entity`.

So the features specified in the PRD are steps towards the essential overarching product requirement of EntitySchema becoming functionally a "Wikibase Entity with a Shape Expression attached to it".
This also means that as a product-wise Wikibase Entity, EntitySchemas will need to be supported in further Wikibase Action API endpoints.
While these requirements are possible to support with the current approach, that would incur significant negative tradeoffs to the maintainability of the EntitySchema codebase and the overall system.
Those tradeoffs are detailed below.

In light of these tradeoffs that would take effect now by moving forward with the current approach,
we need to reevaluate whether it still serves us best for fulfilling our functional and non-functional requirements.

Secondary, non-crucial, but notable context: for a later version, it is intended for EntitySchema to have Statements of their own (see [T345745](https://phabricator.wikimedia.org/T345745)).
Also, in light of the gradual overall move towards RESTful Api endpoints, it is intended for some future version of EntitySchema to be properly editable with such endpoints as well.

This ADR, if accepted, will supersede [0001 Extend Entity Schema to support additional “traits”](./0001-extend-entity-schema-codebase.md).

## Considered options

### Make EntitySchema a pseudo-entity and create limited support for that in Wikibase (current approach)
EntitySchema will continue to not make use of the Entity Registration mechanism in Wikibase.
Instead, EntitySchemas will be made to appear to some parts of Wikibase as an Entity by adding new ways to extend Wikibase.
(For example in EntityId parsers we need to add new hooks to add one to be able to create `EntityId`s that are `EntitySchemaId` instances.
This would directly duplicate functionality of the Entity Registration.)
Other parts, that would require it to be an actual Wikibase Entity, will need to be worked around with further extension points.
(For example, `EntitySchemaId` instances cannot, without further work, be used to load a `Title` in an `EntityTitleLookup`.)

We will further reuse functionality from Wikibase by directly coupling against Wikibase and using its services (for example Language Fallback),
and by reusing shared upstream packages where they exist (for example, the Vue Termbox).
We also will copy code from Wikibase and adjust it for the particular needs of EntitySchema.

#### Benefits
It will be slightly easier for us to locally vary some functionality that would otherwise come from Wikibase, for example using the Vue Termbox on Desktop.

The main Entity registration mechanism will not be used by another Entity type in addition to the 5 that already exist.
Instead Wikibase will be extended by implementing support for non-entities (pseudo-entities).
In the scope of the EntitySchema development, pseudo-entity support will be incrementally added to Wikibase,
but we anticipate that this will only be implemented partially.

#### Costs
It will make the overall system harder to understand, as what is presented to users as a Wikibase Entity is not actually one in code.

By using the datavalue-type `wikibase-entityid` for something that is not actually a Wikibase Entity, we break a fundamental assumption of the Wikibase codebase.
This will result in a lot of overhead not only in review, but also to validate and ensure that everything works in the same way that it did before.
Further it will make future changes to Wikibase harder, because they will not only need to be tested and validated with respect to Wikibase Entities that use the Entity Registration, like Items and Lexemes,
but also with respect to pseudo-entities like EntitySchemas that do not use the Entity Registration but extend Wikibase in a second, partially parallel way.
In particular, this will make it harder and slower to move towards a more modular architecture of Wikibase, because now two ways of extending Wikibase will need to be taken into account, instead of just one.

Put differently: Similar to the proposed approach, this too will introduce tight non-usecase-specific coupling on a low level between Wikibase and EntitySchema in a way that is architecturally undesirable.
Also, and again similar to the proposed approach, this means that it will be harder to reason backwards from the code to the product requirements that are implemented by it.

When we extend the Action API to support EntitySchema in order to implement functional requirements, that will require substantial changes to Wikibase.
These will make that part of Wikibase harder to analyze and much harder to modify in the future (see [T341969#9095587](https://phabricator.wikimedia.org/T341969#9095587)).

For the parts where code implementing shared Wikibase Entity functionality is copied and adjusted, but still required to behave identical to the Wikibase implementation, this will significantly worsen its maintainability:
* That code will be harder to analyze, because understanding it requires understanding the Wikibase implementation.
* It will be harder to change, because with all changes we need to still make sure that the behavior is identical to the Wikibase implementation.
* It will be harder to meaningfully test, because we can only easily test the behavior in EntitySchema, not the actual requirement, that the behavior matches that of a Wikibase Entity.

Further, that duplicated code creates additional cross-team dependencies due to different teams owning duplicate behavior that needs to be kept in sync, increasing the cognitive load for both teams.
This also applies to operations considerations, such as the custom SSR service for the Vue Termbox.

New documentation about pseudo-entities will need to be written. This should cover the concept of pseudo-entities and also explain the new extension mechanisms.
This needs to be gradually revised to capture which parts of Wikibase can handle pseudo-entities (in contrast to features that only support vanilla entities).

### Make EntitySchema a Wikibase Entity type

EntitySchema will be registered as a Wikibase Entity.
It will implement support for Labels, Descriptions and Aliases like Items and Properties, but it will (for now) not have Statements of its own.
Organisationally, this means that for the features which are not EntitySchema specific we largely rely on the infrastructure provided by the platform team as a service.

#### Benefits
The central benefit is that this will make EntitySchema in the code congruent with the product domain of it being a Wikibase Entity to the user.

This will ultimately lower cognitive load as EntitySchema will make use of a shared interface to provide a consistent experience with other Entities using that interface.
This will in particular mean that the EntitySchema code can drop all copies of shared behavior and focus on the aspects that are unique to EntitySchema.

Future fundamental changes to how Wikibase works will have to be made with EntitySchema as a first class dependent in mind.
That way it will be ensured that through direct collaboration there will be migration paths for EntitySchema's needs when any relevant interface is being changed or deprecated.
Same as it will be for Lexeme (and MediaInfo).

This approach will create another opportunity for cross-team collaboration:
Going through the process of Entity Registration for EntitySchema will give us the first-hand context to meaningfully contribute to the documentation of the Entity Registration experience and its future development.

#### Costs
The main cost of this approach, compared to the current alternative, is that we will need to find a way to either migrate the existing EntitySchemas or provide a permanent compatibility layer.
If we decide to do the migration, then it will not be trivial, because it will need to work for Wikidata, potentially wikibase.cloud, and likely on premise Wikibase Suite installations.

Varying some functionality that is shared among (some) Wikibase Entities, for example showing the Vue Termbox on desktop,
might be slightly harder to do because it will require keeping the big picture in mind.

Further, the Entity Registration interface is in its current form far from optimal.
It lacks documentation, spans many concerns, and requires low-level implementations.
This means that we will incur moderate effort overhead while adopting that interface.
Also, this means that the code implementing it might be somewhat harder to understand for the time being,
which can be mitigated with documentation in our implementation
and contributing to the documentation of the Entity Registration interface while we're implementing it.

Put differently: Similar to the current approach, this too will introduce tight non-usecase-specific coupling on a low level between Wikibase and EntitySchema in a way that is architecturally undesirable.
The impact of that will be mitigated by the fact that it follows an already established pattern.
Also, and again similar to the current approach, this means that it will be slightly harder to reason backwards from the code to the product requirements that are implemented by it.
At least when compared to not moving forward with either approach.

The Wikibase Product Platform and Wikidata teams intend to improve that interface in the mid-term.
This will require some further effort to support those changes on the EntitySchema side (in addition to the work that will be needed for WikibaseLexeme).
On the positive side, those improvements should significantly improve the understandability of that interface and its implementations.

By making EntitySchema implement the Entity Registration interface, that interface will become slightly harder to change because there is now one more dependent.
However, with EntitySchema being a very standard Entity type, this effect should be minor,
especially when considering to the complexity already added by WikibaseLexeme or Federated properties.

## Decision
_(Note: this describes the **rejected** decision, which will not be pursued after all. For the rationale of the rejection, see the Gerrit comment of 2023-10-17.)_

Make EntitySchema a Wikibase Entity.

The main reason for this decision is that it will keep the code of EntitySchema aligned with the product domain.
Further, it reduces the cognitive load from maintaining EntitySchema and results in clear and explicit interaction points with related teams.
Additionally, it means that future changes to the architecture of Wikibase will be easier to perform, compared to the current pseudo-entity approach,
because there will be only one way in which Wikibase is extended, not two.

The cost of having to deal with existing Schemas is accepted.
As is the cost of implementing the Entity Registration interface in its current form and of adapting to its further development.

## Consequences

As a consequence of accepting this ADR, there are two immediate technical main priorities:
1. We need to start looking into options to migrate existing EntitySchemas or to provide a compatibility layer
1. We need to scope out the work that is needed to register EntitySchema as a Wikibase Entity.
Ideally, we might want to do that somewhat incrementally, and we need to get an idea on how to approach that.

Also, we'll be contributing to improving the Entity Registration interface within our means by expanding its documentation as we adapt it,
and we will support the further development of that interface when it takes place.
