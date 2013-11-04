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
 * Very basic unit test engine which runs tests using a script from
 * configuration and expects an XUnit compatible result.
 *
 * @group unit
 */
class GenericXUnitTestEngine extends ArcanistBaseUnitTestEngine {
    public function run() {
        $results = $this->runTests();

        return $results;
    }

    private function runTests() {
        $root = $this->getWorkingCopy()->getProjectRoot();
        $script = $this->getConfiguredScript();
        $path = $this->getConfiguredTestResultPath().'/arcanist.xml';

        // Remove existing file so we cannot report old results
        $this->unlink($path);

        $future = new ExecFuture('%C %s', $script, $path);
        $future->setCWD($root);
        try {
            $future->resolvex();
        } catch(CommandException $exc) {
            if ($exc->getError() != 0) {
                throw $exc;
            }
        }

        return $this->parseTestResults($path);
    }
    
    public function parseTestResults($path) {
        $this->parser = new ArcanistXUnitTestResultParser();
        return  $this->parser->parseTestResults(
            Filesystem::readFile($path));
    }

    private function unlink($filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Load, validate, and return the "script" configuration.
     *
     * @return string The shell command fragment to use to run the unit tests.
     *
     * @task config
     */
     private function getConfiguredScript() {
        $key = 'unit.genericxunit.script';
        $config = $this->getConfigurationManager()
         ->getConfigFromAnySource($key);

        if (!$config) {
            throw new ArcanistUsageException(
            "GenericXunitTestEngine: ".
            "You must configure '{$key}' to point to a script to execute.");
        }

        // NOTE: No additional validation since the "script" can be some random
        // shell command and/or include flags, so it does not need to point to some
        // file on disk.

        return $config;
    }
    
    private function getConfiguredTestResultPath() {
        $key = 'unit.genericxunit.result_path';
        $config = $this->getConfigurationManager()
         ->getConfigFromAnySource($key);

        if (!$config) {
            throw new ArcanistUsageException(
            "GenericXunitTestEngine: ".
            "You must configure '{$key}' to point to a path.");
        }

        return $config;
    }
}
