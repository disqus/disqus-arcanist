<?php

/**
 * Uses ESLint to detect errors and potential problems in JavaScript code.
 */
final class ArcanistESLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'JavaScript linting with AST';
  }

  public function getInfoURI() {
    return 'http://eslint.org';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to detect issues with JavaScript source files.',
      'eslint');
  }

  public function getLinterName() {
    return 'ESLint';
  }

  public function getLinterConfigurationName() {
    return 'eslint';
  }

  protected function getDefaultMessageSeverity($severity) {
    if ($severity === 1) {
      return ArcanistLintSeverity::SEVERITY_ADVICE;
    } else {
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getDefaultBinary() {
    return Filesystem::resolvePath(
      './node_modules/.bin/eslint',
      ArcanistWorkingCopyIdentity::newFromPath(getcwd())->getProjectRoot()
    );
  }

  public function getVersion() {
    list($stdout, $stderr) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = array();
    $regex = '/^v(?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install ESLint using `%s`.', 'npm install eslint');
  }

  protected function getMandatoryFlags() {
    $options = array();

    $options[] = '--format=json';

    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $files = null;
    try {
      $files = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      // Something went wrong and we can't decode the output. Exit abnormally.
      throw new PhutilProxyException(
        pht('ESLint returned unparseable output.'),
        $ex);
    }

    $messages = array();
    foreach ($files as $file) {
      foreach ($file['messages'] as $err) {
        $message = new ArcanistLintMessage();
        $message->setPath(idx($file, 'filePath'));
        $message->setLine(idx($err, 'line'));
        $message->setChar(idx($err, 'column'));
        $message->setCode(idx($err, 'ruleId'));
        $message->setName($this->getLinterName());
        $message->setOriginalText(idx($err, 'context'));
        $message->setDescription(idx($err, 'message'));
        $message->setSeverity($this->getLintMessageSeverity(idx($err, 'severity')));

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
