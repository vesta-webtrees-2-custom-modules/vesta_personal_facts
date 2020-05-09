<?php

namespace Cissee\Webtrees\Hook\HookInterfaces;

use Cissee\WebtreesExt\FactPlaceAdditions;
use Cissee\WebtreesExt\FormatPlaceAdditions;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use Vesta\Model\GenericViewElement;
use Vesta\Model\PlaceStructure;

/**
 * base impl of IndividualFactsTabExtenderInterface
 */
trait EmptyIndividualFactsTabExtender {

  protected $factsTabUIElementOrder = 0;

  public function setFactsTabUIElementOrder(int $order): void {
    $this->factsTabUIElementOrder = $order;
  }

  public function getFactsTabUIElementOrder(): int {
    return $this->factsTabUIElementOrder ?? $this->defaultFactsTabUIElementOrder();
  }

  public function defaultFactsTabUIElementOrder(): int {
    return 9999;
  }

  public function hFactsTabRequiresModalVesta(Tree $tree): ?string {
    return null;
  }
  
  public function hFactsTabGetAdditionalFacts(Individual $person) {
    return array();
  }

  public function hFactsTabGetStyleadds() {
    return array();
  }

  public function hFactsTabGetOutputBeforeTab(Individual $person) {
    return new GenericViewElement('', '');
  }

  public function hFactsTabGetOutputAfterTab(Individual $person) {
    return new GenericViewElement('', '');
  }

  public function hFactsTabGetOutputInDBox(Individual $person) {
    return new GenericViewElement('', '');
  }

  public function hFactsTabGetOutputAfterDBox(Individual $person) {
    return new GenericViewElement('', '');
  }

  public function hFactsTabGetFormatPlaceAdditions(PlaceStructure $place) {
    return new FormatPlaceAdditions();
  }

  public function factPlaceAdditions(PlaceStructure $place): ?FactPlaceAdditions {
    return null;
  }
  
  public function hFactsTabGetOutputForAssoRel(
          Fact $event,
          Individual $person,
          Individual $associate,
          $relationship_prefix,
          $relationship_name,
          $relationship_suffix,
          $inverse) {

    //do not return null - null has special semantics ('abort')
    return new GenericViewElement('', '');
  }

  public function hFactsTabGetAdditionalEditControls(
          Fact $event): GenericViewElement {

    return new GenericViewElement('', '');
  }
}
