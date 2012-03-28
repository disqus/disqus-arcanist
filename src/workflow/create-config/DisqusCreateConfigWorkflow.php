<?php
/*
 * Copyright 2011 Disqus, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Configures the current git repository with the arcanist commit template.
 *
 * @group workflow
 */
class DisqusCreateConfigWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **create-config** __path__
          Creates a generic .arcconfig at the given path, or ./.arcconfig if not given
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'path',
    );
  }

  protected function didParseArguments() {
    if (!$this->getArgument('path')) {
      $path = $this->getWorkingDirectory();
    }
    else {
      $path = head($this->getArgument('path'));
    }

    $this->path = $path;
  }

  public function getSupportedRevisionControlSystems() {
    return array('git');
  }

  public function run() {
    $path = realpath($this->path);
    $arc_file = $path.'/.arcconfig';

    if (file_exists($arc_file)) {
      $ok = phutil_console_confirm(
        "There is already a configuration file at '{$arc_file}'. Do ".
        "you want to continue and overwrite the existing file?");
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    $project_id = last(explode('/', $path));

    $template = $this->getDefaultConfig($project_id);

    echo "Creating default .arcconfig at '{$arc_file}'";

    Filesystem::writeFile($arc_file, $template);
  }

  public function getDefaultConfig($project_id) {
    return <<<EOTEXT
{
  "project_id": "{$project_id}",
  "conduit_uri" : "http://phabricator.local.disqus.net/",
  "arcanist_configuration": "DisqusConfiguration",
  "copyright_holder": "Disqus, Inc.",
  "immutable_history": false,
  "differential.field-selector": "DisqusDifferentialFieldSelector",
  "unit_engine": "DisqusUnitTestEngine",
  "lint_engine": "ComprehensiveLintEngine",
  "lint.pep8.options": "--ignore=W391,W292,W293,E501,E225",
  "lint.jshint.prefix": "node_modules/jshint/bin",
  "lint.jshint.bin": "hint"
}
EOTEXT;
  }
}