# Continuous integration preparation

The workflow uses MoodleHQ moodle-plugin-ci, read-only repository permissions and no deployment credentials. It covers Moodle 4.5 through 5.3 on PHP 8.3/MySQL 8.4, and Moodle 5.3 on PHP 8.4/MySQL and PHP 8.3/PostgreSQL 17.

Reference: https://moodlehq.github.io/moodle-plugin-ci/

The workflow is prepared locally and has not run on GitHub. After repository publication, enable Actions, run the workflow and verify every result before publishing a stable release. Check dependency/action releases and Moodle branches at that time. Local evidence does not imply GitHub Actions has passed.

The database credentials in the workflow are disposable runner fixtures. Do not reuse them for a laboratory, hosted site or production deployment. Do not add secrets for ordinary plugin testing.
