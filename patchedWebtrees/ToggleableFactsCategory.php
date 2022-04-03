<?php

namespace Cissee\WebtreesExt;

use Fisharebest\Webtrees\Webtrees;

class ToggleableFactsCategory {

  private $id;
  private $target;
  private $label;

  public function getId() {
    return $this->id;
  }

  public function getTarget() {
    return $this->target;
  }

  public function getLabel() {
    return $this->label;
  }

  /**
   *
   * @param string $id (for webtrees.persistentToggle)
   * @param string $target (styleadd in respective facts)
   * @param string $label
   */
  public function __construct($id, $target, $label) {
    $this->id = $id;
    $this->target = $target;
    $this->label = $label;
    
    if (str_starts_with(Webtrees::VERSION, '2.1')) {
        //class is obsolete.
    }
  }

}
