Workflow Emails
==

The two workflows here send e-mails to WMDE mailing lists

Daily CI
--

The Daily CI job sends an e-mail to `wikidata-ci-status@wikimedia.de` on failure, which is a mailing list managed by the engineering managers.

Weekly Keepalive
--

The Weekly Keepalive job sends a mail every week to `wikidata-ci-bitbucket@wikimedia.de` - a mailing list with no recipients. This is done to keep the credentials active. Google has a [Less secure apps](https://support.google.com/accounts/answer/6010255) setting which allows sending e-mails using the account username and password via SMTP. If these credentials are not used for an "insecure" login for a longer period, the feature is disabled and the sending stops working - see [T365704](https://phabricator.wikimedia.org/T365704).

Email Credentials
--

The credentials used to send the e-mails are those of the `wb-github-ci@wikimedia.de` account, to which the WMDE engineering managers have the password.

