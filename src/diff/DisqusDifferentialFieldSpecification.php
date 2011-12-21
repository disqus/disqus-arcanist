<?php
/**
 * Selector which adds on our Disqus-specific fields.
 */
class DisqusDifferentialFieldSelector extends DifferentialFieldSelector {
  public function getFieldSpecifications() {
    $default = new DifferentialDefaultFieldSelector();
    $list = $default->getFieldSpecifications();
    // XXX: This was suggested to remove the Test Plan requirement
    // foreach ($list as $key => $field) {
    //   if ($field->getCommitMessageKey() == 'testPlan') {
    //     // Remove "Test Plan".
    //     unset($list[$key]);
    //   }
    // }
    $list[] = new CoverageFieldSpecification();
    return $list;
  }
}
?>