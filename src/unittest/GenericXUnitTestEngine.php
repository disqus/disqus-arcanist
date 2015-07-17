<?php

/**
 * Very basic unit test engine which runs tests using a script from
 * configuration and expects an XUnit compatible result.
 *
 * @group unit
 */
class GenericXUnitTestEngine extends ArcanistUnitTestEngine {
    public function run() {
        $results = $this->runTests();

        return $results;
    }

    private function runTests() {
        $root = $this->getWorkingCopy()->getProjectRoot();
        $script = $this->getConfiguredScript();
        $path = $this->getConfiguredTestResultPath();

        foreach (glob($root.DIRECTORY_SEPARATOR.$path."/*.xml") as $filename) {
            // Remove existing files so we cannot report old results
            $this->unlink($filename);
        }

        // Provide changed paths to process
        putenv("ARCANIST_DIFF_PATHS=".implode(PATH_SEPARATOR, $this->getPaths()));

        $future = new ExecFuture('%C %s', $script, $path);
        $future->setCWD($root);
        $err = null;
        try {
            $future->resolvex();
        } catch(CommandException $exc) {
            $err = $exc;
        }

        $results = $this->parseTestResults($root.DIRECTORY_SEPARATOR.$path);

        if ($err) {
            $result = new ArcanistUnitTestResult();
            $result->setName('Unit Test Script');
            $result->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
            $result->setUserData("ERROR: Command failed with code {$err->getError()}\nCOMMAND: `{$err->getCommand()}`");

            $results[] = $result;
        }

        return $results;
    }

    public function parseTestResults($path) {
        $results = array();

        foreach (glob($path."/*.xml") as $filename) {
            $parser = new ArcanistXUnitTestResultParser();
            $results[] = $parser->parseTestResults(
                Filesystem::readFile($filename));
        }

        return array_mergev($results);
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
