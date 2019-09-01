<?php

namespace Cissee\WebtreesExt;

use Vesta\Model\GenericViewElement;

class FactPlaceAdditions {

  private $beforePlace;
  private $afterMap;
  private $afterNotes;

  public function getBeforePlace(): GenericViewElement {
    return $this->beforePlace;
  }
  
  public function getAfterMap(): GenericViewElement {
    return $this->afterMap;
  }

  public function getAfterNotes(): GenericViewElement {
    return $this->afterNotes;
  }

  public function __construct(
          GenericViewElement $beforePlace, 
          GenericViewElement $afterMap, 
          GenericViewElement $afterNotes) {
    
    $this->beforePlace = $beforePlace;
    $this->afterMap = $afterMap;
    $this->afterNotes = $afterNotes;
  }

}
