# The wiring for creating a new EntitySchema Datatype will be in the EntitySchema extension

## Status

Accepted (2023-03-31)

## Context

We want to add a new Datatype to Wikidata, so that EntitySchemas can be the value of a Statement.
See [T332139](https://phabricator.wikimedia.org/T332139).

In the past, this was attempted in a reduced form, see [T214884](https://phabricator.wikimedia.org/T214884) and associated patches.
However, that was judged by the community to not be sufficient and lack important features and so was disabled.

Hence, going forward, we want a more viable implementation. That should include, among others, the following functionality:
* only existing EntitySchemas can be selected
* the link text for values of the EntitySchema Datatype in the Wikidata Item UI (and such) should be the Label of the EntitySchema
* "What Links Here" of an EntitySchema should include links from Statements
* The value of an EntitySchema value should be exported in RDF as a URI

These requirements will need significant wiring code. We have to decide where to place that wiring code.

Also, we previously decided to not register EntitySchema as an entity-type of Wikibase per se (see ADR-0001),
and so this decision here should not contradict that.

## Considered Options

### Placing the wiring for creating a new EntitySchema Datatype in Wikibase

This is what was done when it was tried in the past.

#### Benefits

* the EntitySchema extension can remain Wikibase-agnostic for now

#### Risks

* that means that code about an external extension is in Wikibase, contributing to it being even more confusing
* focusing on keeping the EntitySchema extension Wikibase-agnostic would limit our options with regard to how to proceed with things like language fallback
* integrating with EntitySchema (checking the existence, getting the Label, etc.) might be less "clean".
  (Though viable. See the proof of concept with a change in [Wikibase](https://gerrit.wikimedia.org/r/c/mediawiki/extensions/Wikibase/+/903609) and [EntitySchema](https://gerrit.wikimedia.org/r/c/mediawiki/extensions/EntitySchema/+/903608))
* this approach might introduce further complications if we decide that we want Statements on EntitySchemas in the future
* Integration tests will likely add even more EntitySchema logic to Wikibase

### Placing the wiring for creating a new EntitySchema Datatype in EntitySchema

The WikibaseLexeme, Score and Math extensions would be examples for this approach.
(Score and Math are not Semantic (LOD) Entities, nor are they registering as a Wikibase entity-type.)

#### Benefits

* All EntitySchema code is in EntitySchema
  * This should lead to easier onboarding to the extension,
    and easier reasoning about what is happening inside of it
  * No cross-repo dependencies on EntitySchema code will make that easier to refactor
* This approach makes the coupling clearest: Wikibase publishes a public interface, EntitySchema uses it as intended

#### Risks
* This would introduce direct references and coupling from EntitySchema to Wikibase,
  though Wikibase is expected to remain optional for using EntitySchema in context of this decision
* It will also add a lot of wiring code to EntitySchema.
  * Though that risk can potentially be somewhat mitigated by confining that code to a particular directory,
    for example called `Wikibase/`, similarly to the pattern of having a `MediaWiki/` directory
* We will have to add (some) Wikibase CI to EntitySchema CI, likely causing much longer CI run times


### Placing the wiring for creating a new EntitySchema Datatype in a new third extension

This approach follows the example of what was done in the WikibaseCirrusSearch extension,
connecting the Wikibase and CirrusSearch extensions with each other.

#### Benefits
* Inverting the dependency, both Wikibase and EntitySchema are depended upon by a smaller, very specialized third extension.

#### Risks
* It would be harder to reason across three extensions than across two
* We would have the setup and maintenance overhead of yet another extension
* As this is technically a new Extension, it might need security review?
* In practice, that separation is only happening on the surface,
  because we want all of these things always working together in production,
  and so they have to change together anyway
* This extension will basically always be needed where EntitySchema is used
  because EntitySchema will likely rarely be used without Wikibase
  (whereas Wikibase is often used without CirrusSearch and vice versa)

## Decision

We decide to place the wiring code in the EntitySchema extension,
because keeping the EntitySchema-related code together in one place should make it easier to reason about and refactor,
while avoiding the drawbacks of adding further complexity directly to Wikibase,
or having an entire other extension to deal with.

### Consequences

As a consequence of this decision, the previous wiring code from Wikibase can likely be removed.

However, we now have to include Wikibase CI in EntitySchema CI, which means it will take longer to run.
