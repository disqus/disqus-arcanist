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
 * Very basic unit test engine which runs tests using Nose.
 *
 * Requires the following packages:
 *  - nose-quickunit
 *  - nose >= 1.2
 *  - piplint
 *
 * Makes the assumption that you have a 'runtests.py' file which
 * is your designated nose test runner.
 *
 * @group unitrun
 */
class DisqusUnitTestEngine extends ArcanistBaseUnitTestEngine {
  public function run() {
    $results = $this->runTests();

    return $results;
  }

  private function runTests() {
    $this->checkRequirements();

    $working_copy = $this->getWorkingCopy();
    $project_root = $working_copy->getProjectRoot();

    list($xunit_path, $coverage_path) = $this->runTestSuite($project_root);

    $results = $this->buildTestResults($xunit_path, $coverage_path);

    return $results;
  }

  private function checkRequirements() {
    $working_copy = $this->getWorkingCopy();
    $project_root = $working_copy->getProjectRoot();

    $piplint_files = $working_copy->getConfig('unit.piplint.files');
    if (empty($piplint_files)) {
      return;
    }

    $args = array('piplint');
    $args = array_merge($args, $piplint_files);

    $cmd = implode(' ', $args);

    $future = new ExecFuture($cmd);
    $future->setCWD($project_root);

    $future->resolvex();
  }

  private function getPythonPaths(){
    $results = array();
    foreach ($this->getPaths() as $path) {
      if (substr($path, -3) == '.py') {
        $results[] = $path;
      }
    }
    return $results;
  }

  private function runTestSuite($project_root) {
    if (!file_exists($project_root.'/runtests.py')) {
      return array();
    }
    $xunit_path = $project_root.'/test_results/nosetests.xml';
    $coverage_path = $project_root.'/test_results/coverage.xml';

    // Remove existing files so we cannot report old results
    if (file_exists($xunit_path)) {
      unlink($xunit_path);
    }

    if (file_exists($coverage_path)) {
      unlink($coverage_path);
    }

    $pythonPaths = $this->getPythonPaths();

    if ($this->getEnableCoverage() !== false) {
      $future = new ExecFuture("%C", csprintf('coverage run runtests.py --with-quickunit'.
          ' --with-xunit --xunit-file=%s', $xunit_path));
      $future->setCWD($project_root);
      $future->resolvex();

      // If we run coverage with only non-python files it will error
      if (!empty($pythonPaths)) {
        try {
          $future = new ExecFuture("%C", csprintf('coverage xml -o %s --include=%s', $coverage_path, implode(',', $pythonPaths)));
          $future->setCWD($project_root);
          $future->resolvex();
        } catch (Exception $ex) {
          // we dont care about this exception
        }
      }
    } else {
      $future = new ExecFuture("%C", csprintf('python runtests.py --with-quickunit'.
          ' --with-xunit --xunit-file=%s', $xunit_path));
      $future->setCWD($project_root);
      $future->resolvex();
    }
    return array($xunit_path, $coverage_path);
  }

  private function buildTestResults($xunit_path, $coverage_path) {
    if ($this->getEnableCoverage() !== false && file_exists($coverage_path)) {
      $coverage_report = $this->readCoverage($coverage_path);
    } else {
      $coverage_report = null;
    }

    $xunit_dom = new DOMDocument();
    $xunit_dom->loadXML(Filesystem::readFile($xunit_path));

    $results = array();
    $testcases = $xunit_dom->getElementsByTagName("testcase");
    foreach ($testcases as $testcase) {
      $classname = $testcase->getAttribute("classname");
      $name = $testcase->getAttribute("name");
      $time = $testcase->getAttribute("time");

      $status = ArcanistUnitTestResult::RESULT_PASS;
      $user_data = "";

      // A skipped test is a test which was ignored using framework
      // mechanizms (e.g. @skip decorator)
      $skipped = $testcase->getElementsByTagName("skipped");
      if ($skipped->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_SKIP;
        $messages = array();
        for ($ii = 0; $ii < $skipped->length; $ii++) {
          $messages[] = trim($skipped->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages);
      }

      // Failure is a test which the code has explicitly failed by using
      // the mechanizms for that purpose. e.g., via an assertEquals
      $failures = $testcase->getElementsByTagName("failure");
      if ($failures->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
        $messages = array();
        for ($ii = 0; $ii < $failures->length; $ii++) {
          $messages[] = trim($failures->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      // An errored test is one that had an unanticipated problem. e.g., an
      // unchecked throwable, or a problem with an implementation of the
      // test.
      $errors = $testcase->getElementsByTagName("error");
      if ($errors->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_BROKEN;
        $messages = array();
        for ($ii = 0; $ii < $errors->length; $ii++) {
          $messages[] = trim($errors->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      $result = new ArcanistUnitTestResult();
      $result->setName($classname.".".$name);
      $result->setResult($status);
      $result->setDuration($time);
      $result->setUserData($user_data);
      // this is technically incorrect, but since phabricator aggregates it we dont care
      if ($coverage_report !== null) {
        $result->setCoverage($coverage_report);
      }

      $results[] = $result;
    }

    return $results;
  }

  public function readCoverage($path) {
    $coverage_data = Filesystem::readFile($path);
    if (empty($coverage_data)) {
      return array();
    }

    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML($coverage_data);

    $paths = $this->getPaths();
    $reports = array();
    $classes = $coverage_dom->getElementsByTagName("class");

    foreach ($classes as $class) {
      // filename is actually python module path with ".py" at the end,
      // e.g.: tornado.web.py
      $relative_path = explode(".", $class->getAttribute("filename"));
      array_pop($relative_path);
      $relative_path = implode("/", $relative_path);

      // first we check if the path is a directory (a Python package), if it is
      // set relative and absolute paths to have __init__.py at the end.
      $absolute_path = Filesystem::resolvePath($relative_path);
      if (is_dir($absolute_path)) {
        $relative_path .= "/__init__.py";
        $absolute_path .= "/__init__.py";
      }

      // then we check if the path with ".py" at the end is file (a Python
      // submodule), if it is - set relative and absolute paths to have
      // ".py" at the end.
      if (is_file($absolute_path.".py")) {
        $relative_path .= ".py";
        $absolute_path .= ".py";
      }

      if (!file_exists($absolute_path)) {
        continue;
      }

      if (!in_array($relative_path, $paths)) {
        continue;
      }

      // get total line count in file
      $line_count = count(file($absolute_path));

      $coverage = "";
      $start_line = 1;
      $lines = $class->getElementsByTagName("line");
      for ($ii = 0; $ii < $lines->length; $ii++) {
        $line = $lines->item($ii);

        $next_line = intval($line->getAttribute("number"));
        for ($start_line; $start_line < $next_line; $start_line++) {
          $coverage .= "N";
        }

        if (intval($line->getAttribute("hits")) == 0) {
          $coverage .= "U";
        }
        else if (intval($line->getAttribute("hits")) > 0) {
          $coverage .= "C";
        }

        $start_line++;
      }

      if ($start_line < $line_count) {
        foreach (range($start_line, $line_count) as $line_num) {
          $coverage .= "N";
        }
      }

      $reports[$relative_path] = $coverage;
    }

    return $reports;
  }

}
