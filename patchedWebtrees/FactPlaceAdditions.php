<?php

namespace Cissee\WebtreesExt;

use Vesta\Model\GenericViewElement;

class FactPlaceAdditions {

  private $afterMap;
  private $afterNotes;

  public function getAfterMap(): GenericViewElement {
    return $this->afterMap;
  }

  public function getAfterNotes(): GenericViewElement {
    return $this->afterNotes;
  }

  public function __construct(GenericViewElement $afterMap, GenericViewElement $afterNotes) {
    $this->afterMap = $afterMap;
    $this->afterNotes = $afterNotes;
  }

}
