<?php

/**
 * Basic lint engine which just applies several linters based on the file types
 *
 * @group linter
 */
class DisqusLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $linters = array();

    $paths = $this->getPaths();

    // This needs to go first so that changes to generated files cause module
    // linting. This linter also operates on removed files, because removing
    // a file changes the static properties of a module.
    $module_linter = new ArcanistPhutilModuleLinter();
    $linters[] = $module_linter;
    foreach ($paths as $path) {
      $module_linter->addPath($path);
    }

    // Remaining lint engines operate on file contents and ignore removed
    // files.
    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path)) {
        unset($paths[$key]);
      }
      if (preg_match('@^externals/@', $path)) {
        // Third-party stuff lives in /externals/; don't run lint engines
        // against it.
        unset($paths[$key]);
      }
    }

    $generated_linter = new ArcanistGeneratedLinter();
    $linters[] = $generated_linter;

    $nolint_linter = new ArcanistNoLintLinter();
    $linters[] = $nolint_linter;

    $text_linter = new ArcanistTextLinter();
    // disable max line length warnings
    $text_linter->setCustomSeverityMap(array(ArcanistTextLinter::LINT_LINE_WRAP => ArcanistLintSeverity::SEVERITY_DISABLED));
    $linters[] = $text_linter;
    foreach ($paths as $path) {
      $is_text = false;
      if (preg_match('/\.(php|css|js|hpp|cpp|l|y)$/', $path)) {
        $is_text = true;
      }
      if ($is_text) {
        $generated_linter->addPath($path);
        $generated_linter->addData($path, $this->loadData($path));

        $nolint_linter->addPath($path);
        $nolint_linter->addData($path, $this->loadData($path));

        $text_linter->addPath($path);
        $text_linter->addData($path, $this->loadData($path));
      }
    }

    $name_linter = new ArcanistFilenameLinter();
    $linters[] = $name_linter;
    foreach ($paths as $path) {
      $name_linter->addPath($path);
    }

    $xhpast_linter = new ArcanistXHPASTLinter();
    $license_linter = new ArcanistApacheLicenseLinter();
    $linters[] = $xhpast_linter;
    $linters[] = $license_linter;
    foreach ($paths as $path) {
      if (preg_match('/\.php$/', $path)) {
        $xhpast_linter->addPath($path);
        $xhpast_linter->addData($path, $this->loadData($path));
      }
    }

    foreach ($paths as $path) {
      if (preg_match('/\.(php|cpp|hpp|l|y)$/', $path)) {
        if (!preg_match('@^externals/@', $path)) {
          $license_linter->addPath($path);
          $license_linter->addData($path, $this->loadData($path));
        }
      }
    }

    $py_linter = new ArcanistPyFlakesLinter();
    $pep8_linter = new DisqusPEP8Linter();
    $pytext_linter = new ArcanistTextLinter();
    $pytext_linter->setCustomSeverityMap(array(
      ArcanistTextLinter::LINT_BAD_CHARSET => ArcanistLintSeverity::SEVERITY_DISABLED,
      ArcanistTextLinter::LINT_LINE_WRAP => ArcanistLintSeverity::SEVERITY_DISABLED,
    ));
    $linters[] = $py_linter;
    $linters[] = $pep8_linter;
    $linters[] = $pytext_linter;
    foreach ($paths as $path) {
      if (preg_match('/\.py$/', $path)) {
        $py_linter->addPath($path);
        $py_linter->addData($path, $this->loadData($path));
        $pep8_linter->addPath($path);
        $pep8_linter->addData($path, $this->loadData($path));
        $pytext_linter->addPath($path);
        $pytext_linter->addData($path, $this->loadData($path));
      }
    }

    return $linters;
  }

}