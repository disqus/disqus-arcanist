<?php

/*
 * Copyright 2013 Disqus, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * A unit test engine which runs tests using configurable commands and assuming
 * XUnit output.
 *
 * Requires the following packages:
 *    - piplint
 *
 * Available configuration options are:
 *  - unit.disqus.python
 *  - unit.disqus.python+coverage
 *  - unit.disqus.javascript
 *  - unit.disqus.javascript+coverage
 *  - unit.piplint.files
 *
 * All the options above, except for unit.piplint.files, are strings that are
 * formatted using the "%s" pattern and receive the following arguments, in
 * order: Xunit test result file path, Coverage result file path, changed paths
 *
 * Sample config:
 *   "unit.disqus.python+coverage": "coverage run runtests.py --with-xunit --with-quickunit --xunit-file=%s && coverage xml -o %s --include=%s"
 *
 * unit.piplint.files is simply an array of pip requirement file paths
 *
 * @group unitrun
 */
class DisqusUnitTestEngine extends ArcanistBaseUnitTestEngine {
    public function run() {
        $this->checkRequirements();

        $working_copy = $this->getWorkingCopy();
        $project_root = $working_copy->getProjectRoot();

        list($xunit_path, $coverage_path) = $this->runTestSuite($project_root);
        $python_results = $this->buildTestResults($xunit_path, $coverage_path);

        list($xunit_path, $coverage_path) = $this->runTestSuite($project_root, true);
        $js_results = $this->buildTestResults($xunit_path, $coverage_path);

        return array_merge($python_results, $js_results);
    }

    private function checkRequirements() {
        $working_copy = $this->getWorkingCopy();
        $project_root = $working_copy->getProjectRoot();
        
        $piplint_files = $this->getConfigurationManager()
         ->getConfigFromAnySource('unit.piplint.files');

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

    private function getJavaScriptPaths(){
        $results = array();
        foreach ($this->getPaths() as $path) {
            if (substr($path, -3) == '.js') {
                $results[] = $path;
            }
        }
        return $results;
    }

    private function unlink($filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    private function runTestSuite($project_root, $js=false) {
        $paths = $js ? $this->getJavaScriptPaths() : $this->getPythonPaths();
        if (empty($paths)) {
            return array(null, null);
        }
        
        $config_manager = $this->getConfigurationManager();

        $type = $js ? 'javascript' : 'python';
        $xunit_path = $project_root.'/test_results/'.$type.'_tests.xml';
        $coverage_path = $project_root.'/test_results/'.$type.'_coverage.xml';

        // Remove existing file so we cannot report old results
        $this->unlink($xunit_path);
        $this->unlink($coverage_path);

        $key_name = 'unit.disqus.'.$type;
        $key_name_with_coverage = $key_name.'+coverage';
        
        $runtests_command = null;
        if ($this->getEnableCoverage() !== false) {
            $runtests_command = $config_manager
              ->getConfigFromAnySource($key_name_with_coverage);
        }
        
        // Config may not provide something with coverage
        if (!$runtests_command) {
            $coverage_path = null;
            $runtests_command = $config_manager->getConfigFromAnySource($key_name);
        }

        // csprintf, which is used by ExecFuture, complains when you pass more
        // arguments that are used in the "formatter string" so slice the
        // arguments array to prevent that in case people simply skip extra
        // arguments.
        $num_args_used = preg_match_all('/[^%]%s/', $runtests_command, $matches);
        $args = array_slice(
            array($runtests_command, $xunit_path, $coverage_path, implode(',', $paths)),
            0,
            $num_args_used + 1
        );
        $exec_future_reflection = new ReflectionClass('ExecFuture');
        $future = $exec_future_reflection->newInstanceArgs($args);
        $future->setCWD($project_root);
        try {
            $future->resolvex();
        } catch(CommandException $exc) {
            if ($exc->getError() > 1) {
                // 'nose' returns 1 when tests are failing/broken.
                throw $exc;
            }
        }

        return array($xunit_path, $coverage_path);
    }

    private function buildTestResults($xunit_path, $coverage_path=null) {
        if (!file_exists($xunit_path)) {
            return array();
        }

        $this->parser = new ArcanistXUnitTestResultParser();
        $results = $this->parser->parseTestResults(Filesystem::readFile($xunit_path));
        
        if (file_exists($coverage_path)) {
            $coverage_report = $this->readCoverage($coverage_path);
            foreach ($results as $result) {
                $result->setCoverage($coverage_report);
            }
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
