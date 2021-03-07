<?php

namespace Cissee\Webtrees\Module\PersonalFacts\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

class WhatsNew1 implements WhatsNewInterface {
  
  public function getMessage(): string {
    return "Vesta Personal Facts: Supports the non-standard Gedcom _FSFTID (FamilySearch id) tag, in the tab and in the newly added sidebar.";
  }
}
