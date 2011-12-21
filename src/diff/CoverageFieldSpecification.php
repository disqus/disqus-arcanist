<?php
/**
 * Adds a pane which displays test coverage information.
 */
class CoverageFieldSpecification extends DifferentialFieldSpecification {
  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Coverage';
  }

  public function renderValueForRevisionView() {
    // return html here
    // getDiffProperty('disqus:coverage')
  }

  public function getRequiredDiffProperties() {
    return array('disqus:coverage');
  }
}
?>