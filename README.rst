Phabricator Extensions
----------------------

This repository is a collection (known as libdisqus) of extensions for `Phabricator <http://phabricator.org/>`_, a tool
originally open sourced by Facebook for task management and code review.

DisqusUnitTestEngine
====================

A basic implementation of a test runner that uses nose and nose-json to collect test results and submit them upstream
with differentials.

Add to your client's .arcconfig::

    {
      "unit_engine": "DisqusUnitTestEngine",
    }

DisqusLintEngine
================

Implements most of the basic linters provided by Arcanist, in addition to a modified PEP8 linter (slightly less strict),
and an additional JSHint linter.

Add to your client's .arcconfig::

    {
      "lint_engine": "DisqusLintEngine",
    }

ProjectAssignmentEventListener
==============================

Automatically assigns an owner to a new task (where one is not set) based on the first assigned project's ownership.

Add to your Phabricator's config::

    'events.listeners' => array(
      'ProjectAssignmentEventListener',
    ),