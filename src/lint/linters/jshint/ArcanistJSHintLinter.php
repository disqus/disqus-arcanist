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

class ArcanistJSHintLinter extends ArcanistLinter {
  public function getLinterName() {
    return 'JSHint';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function getJSHintOptions() {
    $reporter = dirname(realpath(__FILE__)).'/reporter.js';
    return '--reporter '.$reporter;
  }

  public function willLintPaths(array $paths) {
    return;
  }

  public function lintPath($path) {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $jshint_bin = $working_copy->getConfig('jshint_bin');
    $jshint_options = $this->getJSHintOptions();

    list($rc, $stdout) = exec_manual("{$jshint_bin} %s ${jshint_options}",
      $this->getEngine()->getFilePathOnDisk($path));

    if ($rc === 0) {
      return;
    }

    $errors = json_decode($stdout);
    foreach ($errors as $err) {
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($err->line);
      $message->setCode($this->getLinterName());
      $message->setDescription($err->reason);
      $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
      $this->addLintMessage($message);
    }
  }
}
