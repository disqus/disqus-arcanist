<?php

// Taken nearly wholesale from https://github.com/boboli/arcanist-django

final class PyTestUnitTestEngine extends PythonBaseUnitTestEngine
{
    // allow users to specify any additional args to put onto the end of py.test
    function getAdditionalManageArgs()
    {
        return $this->getConfig(
            "unit.engine.args",
            ""
        );
    }

    function getTestPaths()
    {
        return $this->getConfig(
            "unit.engine.test_paths",
            ""
        );
    }


    function getAdditionalEnvVars()
    {
        return $this->getConfig(
            "unit.engine.env",
            "PYTHONUNBUFFERED=1 PYTHONDONTWRITEBYTECODE=1"
        );
    }

    function getPythonCommand()
    {
        return "py.test";
    }

    function getPythonTestCommand($testFile)
    {
        $additionalArgs = $this->getAdditionalManageArgs();
        $environmentVars = $this->getAdditionalEnvVars();
        $paths = $this->getTestPaths();
        $cmd = $this->getPythonCommand();

        return "$environmentVars $cmd $paths -vv $additionalArgs 2>&1";
    }
}
