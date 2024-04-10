<?php

namespace Cissee\Webtrees\Module\PersonalFacts\WhatsNew;

use Cissee\WebtreesExt\WhatsNew\WhatsNewInterface;

//obsolete in webtrees 2.1
class WhatsNew1_Obsolete implements WhatsNewInterface {

  public function getMessage(): string {
    return "Vesta Personal Facts: Supports the non-standard Gedcom _FSFTID (FamilySearch id) tag, in the tab and in the newly added sidebar.";
  }
}
