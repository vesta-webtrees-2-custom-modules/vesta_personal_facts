<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\Contracts\AssociateFactUtils;
use Cissee\WebtreesExt\Functions\FunctionsFactAssociates;
use Cissee\WebtreesExt\GedcomCode\GedcomCodeRela_Ext;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Services\RelationshipService;
use Vesta\Model\GenericViewElement;
use function app;
use function view;

class DefaultAssociateFactUtils implements AssociateFactUtils {
    
    public function gveLabelForAsso(
        ModuleInterface $module,
        string $label,
        Fact $fact,
        Individual $record): GenericViewElement {
        
        $main = '';
        $script = '';

        $parent = $fact->record();
        $tag = explode(':', $fact->tag())[1];

        $label_persons = array();
        if ($parent instanceof Family) {
            // Family event
            //we want husband + wife
            //(even if only one of them may be 'close relative')
            $label_persons = $parent->spouses();
        } else {
            $label_persons[] = $parent;
        }

        $rela = null;
        $inverted = null;
        $label2 = null;

        // Is there a "RELA" tag (code adjusted from elsewhere - note though that strictly according to the Gedcom spec, RELA is mandatory!)
        //(note: this requires adjustment in IndividualFactsTabModule*, where this 'virtual' fact is created)
        if (preg_match('/\n[23] RELA (.+)/', $fact->gedcom(), $rmatch)) {
            $rela = $rmatch[1];
        }
        
        if ($parent instanceof Family) {
            //skip
        } else {
            if ($rela !== null) {
                // Use the supplied relationship - inverted - as a label
                $inverted = GedcomCodeRela_Ext::invert($rela);
                if ($inverted !== null) {
                    $label2 = GedcomCodeRela_Ext::getValue($inverted, $parent);
                } else {
                    //cannot invert: skip
                    //$label2 = 'non-inversible';
                }
            } else {
                //skip
            }
        }

        //handle common cases with explicit translations
        $finalLabel = null;
        switch ($tag) {
            case 'MARR':
                $finalLabel = GedcomCodeRela_Ext::getValueOrNullForMARR($rela, $parent);
                break;
            case 'CHR':
                if ($inverted !== null) {
                  $finalLabel = GedcomCodeRela_Ext::getValueOrNullForCHR($inverted, $parent);
                }
                break;
            case 'BAPM':
                if ($inverted !== null) {
                  $finalLabel = GedcomCodeRela_Ext::getValueOrNullForBAPM($inverted, $parent);
                }
                break;
            default:
                break;
        }

        if ($finalLabel) {
            $main = $finalLabel;
        } else {
            $main = $label;
            if ($label2) {
                $main .= ": " . $label2;
            } else {
                $main .= ": " . MoreI18N::xlate('Associate');
            }
        }

        foreach ($label_persons as $label_person) {
            $relationship_name = app(RelationshipService::class)->getCloseRelationshipName($record, $label_person);
            if ($relationship_name === '') {
                //RC adjusted
                $relationship_name = MoreI18N::xlate('No relationship found');
            }

            //[RC] ADJUSTED (this part wouldn't be in main webtrees)
            $prefix = '(';
            $suffix = ')';
            if ($parent instanceof Family) {
                // For family ASSO records (e.g. MARR), identify the spouse with a sex icon
                $sex = '<small>' . view('icons/sex', ['sex' => $label_person->sex()]) . '</small>';
                $suffix = $sex . ')';
            }
      
            $val = FunctionsFactAssociates::getOutputForRelationship(
                $module,
                $fact, 
                $label_person, 
                $record, 
                $prefix, 
                $relationship_name, 
                $suffix, 
                true);
      
            if ($val != null) {
                $main .= "<br/>";
                $main .= $val->getMain();
                $script .= $val->getScript();
            }
        }
    
        return new GenericViewElement($main, $script);
    }
}