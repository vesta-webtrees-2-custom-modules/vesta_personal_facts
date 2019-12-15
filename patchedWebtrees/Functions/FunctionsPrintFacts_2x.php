<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\WebtreesExt\GedcomCode\GedcomCodeRela_Ext;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeAdop;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeRela;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Services\UserService;
use Vesta\Model\GenericViewElement;
use function app;
use function route;

//[RC] adapted: some methods as non-static for easier extensibility
//and other improvements
/**
 * Class FunctionsPrintFacts - common functions
 */
class FunctionsPrintFacts_2x {

  protected $functionsPrint;

  function __construct($functionsPrint) {
    $this->functionsPrint = $functionsPrint;
  }

  //[RC] adjusted, these were static in printFact 
  protected $children = [];
  protected $grandchildren = [];

  //[RC] added
  protected function additionalStyleadds(Fact $fact, $styleadd) {
    // Event of close relative
    if (preg_match('/^_[A-Z_]{3,5}_[A-Z0-9]{4}$/', $fact->getTag())) {
      $styleadd = trim($styleadd . ' wt-relation-fact collapse');
    }

    // Event of close associates
    if ($fact->id() == 'asso') {
      $styleadd = trim($styleadd . ' wt-relation-fact collapse');
    }

    // historical facts
    if ($fact->id() == 'histo') {
      $styleadd = trim($styleadd . ' wt-historic-fact collapse');
    }

    return $styleadd;
  }

  /**
   * Print a fact record, for the individual/family/source/repository/etc. pages.
   *
   * Although a Fact has a parent object, we also need to know
   * the GedcomRecord for which we are printing it. For example,
   * we can show the death of X on the page of Y, or the marriage
   * of X+Y on the page of Z. We need to know both records to
   * calculate ages, relationships, etc.
   *
   * @param Fact $fact
   * @param GedcomRecord $record
   */
  public function printFactAndReturnScript(Fact $fact, GedcomRecord $record) {
    // Keep a track of children and grandchildren, so we can display their birth order "#1", "#2", etc.
    //static $children = [], $grandchildren = [];

    $script = '';

    $parent = $fact->record();
    $tree = $parent->tree();

    // Some facts don't get printed here ...
    switch ($fact->getTag()) {
      case 'NOTE':
        FunctionsPrintFacts::printMainNotes($fact, 1);

        return;
      case 'SOUR':
        FunctionsPrintFacts::printMainSources($fact, 1);

        return;
      case 'OBJE':
        FunctionsPrintFacts::printMainMedia($fact, 1);

        return;
      case 'FAMC':
      case 'FAMS':
      case 'CHIL':
      case 'HUSB':
      case 'WIFE':
        // These are internal links, not facts
        return;
      case '_WT_OBJE_SORT':
        // These links are used internally to record the sort order.
        return;
      default:
        // Hide unrecognized/custom tags?
        if ($fact->record()->tree()->getPreference('HIDE_GEDCOM_ERRORS') === '0' && !GedcomTag::isTag($fact->getTag())) {
          return;
        }
        break;
    }

    // Who is this fact about? Need it to translate fact label correctly
    if ($parent instanceof Family && $record instanceof Individual) {
      // Family event
      $label_person = $fact->record()->spouse($record);
    } else {
      // Individual event
      $label_person = $parent;
    }

    // New or deleted facts need different styling
    $styleadd = '';
    if ($fact->isPendingAddition()) {
      $styleadd = 'new';
    }
    if ($fact->isPendingDeletion()) {
      $styleadd = 'old';
    }

    //[RC] added/ refactored
    $styleadd = $this->additionalStyleadds($fact, $styleadd);

    // Does this fact have a type?
    if (preg_match('/\n2 TYPE (.+)/', $fact->gedcom(), $match)) {
      $type = $match[1];
    } else {
      $type = '';
    }

    switch ($fact->getTag()) {
      case 'EVEN':
      case 'FACT':
        if (GedcomTag::isTag($type)) {
          // Some users (just Meliza?) use "1 EVEN/2 TYPE BIRT". Translate the TYPE.
          $label = GedcomTag::getLabel($type, $label_person);
          $type = ''; // Do not print this again
        } elseif ($type) {
          // We don't have a translation for $type - but a custom translation might exist.
          $label = I18N::translate(e($type));
          $type = ''; // Do not print this again
        } else {
          // An unspecified fact/event
          $label = $fact->label();
        }
        break;
      case 'MARR':
        // This is a hack for a proprietory extension. Is it still used/needed?
        $utype = strtoupper($type);
        if ($utype == 'CIVIL' || $utype == 'PARTNERS' || $utype == 'RELIGIOUS') {
          $label = GedcomTag::getLabel('MARR_' . $utype, $label_person);
          $type = ''; // Do not print this again
        } else {
          $label = $fact->label();
        }
        break;
      default:
        // Normal fact/event
        $label = $fact->label();
        break;
    }

    echo '<tr class="', $styleadd, '">';
    echo '<th scope="row">';

    switch ($fact->getTag()) {
      case '_BIRT_CHIL':
        $children[$fact->record()->xref()] = true;
        $label .= '<br>' . /* I18N: Abbreviation for "number %s" */ I18N::translate('#%s', count($children));
        break;
      case '_BIRT_GCHI':
      case '_BIRT_GCH1':
      case '_BIRT_GCH2':
        $grandchildren[$fact->record()->xref()] = true;
        $label .= '<br>' . /* I18N: Abbreviation for "number %s" */ I18N::translate('#%s', count($grandchildren));
        break;
    }

    //[RC] adjusted
    if ($fact->id() !== 'asso') {
      echo $label;
    } //else echo later, we want to handle a few special cases

    //[RC] added additional edit controls, even if fact itself is not editable
    $additionalControls = $this->printAdditionalEditControls($fact);
    $main = $additionalControls->getMain();
    $script .= $additionalControls->getScript();

    //[RC] meh - why not just use a non-editable Fact subclass for histo? (see VirtualFact)
    //rather hacky to have the special check here!
    //[RC] adjusted - should be webtrees issue:
    //asso facts may be editable per se, but not like this (i.e. via the 'asso' fact id)
    if (($main !== '') || (($fact->id() != 'histo') && ($fact->id() !== 'asso') && $fact->canEdit())) {
      echo '<div class="editfacts">';
      
      if (($fact->id() != 'histo') && ($fact->id() !== 'asso') && $fact->canEdit()) {
        echo view('edit/icon-fact-edit', ['fact' => $fact]);
        echo view('edit/icon-fact-copy', ['fact' => $fact]);
        echo view('edit/icon-fact-delete', ['fact' => $fact]);
      }
      echo $main;
      echo '</div>';
    }
    
    //[RC] added, this could be in main webtrees
    if ($fact->id() === 'asso') {
      if ($record instanceof Individual) { //check: anything else actually possible here?
        $label_persons = array();
        if ($parent instanceof Family) {
          // Family event
          $label_persons = $parent->spouses();
          //original assignment:
          //$label_person = $fact->record()->spouse($record);
          //wasn't intended for asso, only accidentally worked here somewhat, but we want husband + wife anyway
          //(even if only one of them may be 'close relative')
        } else {
          //original assignment is ok in this case
          $label_persons[] = $label_person;
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
              $label2 = GedcomCodeRela::getValue($inverted, $label_person);
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
        switch ($fact->getTag()) {
          case 'MARR':
            $finalLabel = GedcomCodeRela_Ext::getValueOrNullForMARR($rela, $label_person);
            break;
          case 'CHR':
            if ($inverted !== null) {
              $finalLabel = GedcomCodeRela_Ext::getValueOrNullForCHR($inverted, $label_person);
            }
            break;
          case 'BAPM':
            if ($inverted !== null) {
              $finalLabel = GedcomCodeRela_Ext::getValueOrNullForBAPM($inverted, $label_person);
            }
            break;
          default:
            break;
        }

        if ($finalLabel) {
          echo $finalLabel;
        } else {
          echo $label;
          if ($label2) {
            echo ": " . $label2;
          }
        }

        foreach ($label_persons as $label_person) {
          $relationship_name = Functions::getCloseRelationshipName($record, $label_person);
          if (!$relationship_name) {
            //RC adjusted
            $relationship_name = I18N::translate('No relationship found');
          }

          //[RC] ADJUSTED (this part wouldn't be in main webtrees)
          $suffix = ')';
          if ($parent instanceof Family) {
            // For family ASSO records (e.g. MARR), identify the spouse with a sex icon
            $sex = '<small>' . view('icons/sex-' . $label_person->sex()) . '</small>';
            $suffix = $sex . ')';
          }
          $value = $this->getOutputForRelationship($fact, $label_person, $record, '(', $relationship_name, $suffix, true);
          if ($value != null) {
            echo "<br/>";
            echo $value->getMain();
            $script .= $value->getScript();
          }
        }
      }
    }
    
    //[RC] added end
    if ($tree->getPreference('SHOW_FACT_ICONS')) {
      echo '<span class="wt-fact-icon wt-fact-icon-' . $fact->getTag() . '" title="' . strip_tags(GedcomTag::getLabel($fact->getTag())) . '"></span>';
    }
        
    echo '</th>';
    echo '<td class="', $styleadd, '">';

    // Event from another record?
    if ($parent !== $record) {
      if ($parent instanceof Family) {
        foreach ($parent->spouses() as $spouse) {
          if ($record !== $spouse) {
            echo '<a href="', e($spouse->url()), '">', $spouse->fullName(), '</a> — ';
          }
        }
        echo '<a href="', e($parent->url()), '">', I18N::translate('View this family'), '</a><br>';
      } elseif ($parent instanceof Individual) {
        echo '<a href="', e($parent->url()), '">', $parent->fullName(), '</a><br>';
      }
    }

    // Print the value of this fact/event
    switch ($fact->getTag()) {
      case 'ADDR':
        echo $fact->value();
        break;
      case 'AFN':
        echo '<div class="field"><a href="https://familysearch.org/search/tree/results#count=20&query=afn:', rawurlencode($fact->value()), '">', e($fact->value()), '</a></div>';
        break;
      case 'ASSO':
        // we handle this later, in format_asso_rela_record()
        break;
      case 'EMAIL':
      case 'EMAI':
      case '_EMAIL':
        echo '<div class="field"><a href="mailto:', e($fact->value()), '">', e($fact->value()), '</a></div>';
        break;
      case 'RESN':
        echo '<div class="field">';
        switch ($fact->value()) {
          case 'none':
            // Note: "1 RESN none" is not valid gedcom.
            // However, webtrees privacy rules will interpret it as "show an otherwise private record to public".
            echo '<i class="icon-resn-none"></i> ', I18N::translate('Show to visitors');
            break;
          case 'privacy':
            echo '<i class="icon-class-none"></i> ', I18N::translate('Show to members');
            break;
          case 'confidential':
            echo '<i class="icon-confidential-none"></i> ', I18N::translate('Show to managers');
            break;
          case 'locked':
            echo '<i class="icon-locked-none"></i> ', I18N::translate('Only managers can edit');
            break;
          default:
            echo e($fact->value());
            break;
        }
        echo '</div>';
        break;
      case 'PUBL': // Publication details might contain URLs.
        echo '<div class="field">', Filter::expandUrls($fact->value(), $record->tree()), '</div>';
        break;
      case 'REPO':
        $repository = $fact->target();
        if ($repository !== null) {
          echo '<div><a class="field" href="', e($repository->url()), '">', $repository->fullName(), '</a></div>';
        } else {
          echo '<div class="error">', e($fact->value()), '</div>';
        }
        break;
      case 'URL':
      case '_URL':
      case 'WWW':
        echo '<div class="field"><a href="', e($fact->value()), '">', e($fact->value()), '</a></div>';
        break;
      case 'TEXT': // 0 SOUR / 1 TEXT
        echo '<div class="field">', nl2br(e($fact->value()), false), '</div>';
        break;
      default:
        // Display the value for all other facts/events
        switch ($fact->value()) {
          case '':
            // Nothing to display
            break;
          case 'N':
            // Not valid GEDCOM
            echo '<div class="field">', I18N::translate('No'), '</div>';
            break;
          case 'Y':
            // Do not display "Yes".
            break;
          default:
            if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $fact->value(), $match)) {
              $target = GedcomRecord::getInstance($match[1], $fact->record()->tree());
              if ($target) {
                echo '<div><a href="', e($target->url()), '">', $target->fullName(), '</a></div>';
              } else {
                echo '<div class="error">', e($fact->value()), '</div>';
              }
            } else {
              echo '<div class="field"><span dir="auto">', e($fact->value()), '</span></div>';
            }
            break;
        }
        break;
    }

    // Print the type of this fact/event
    if ($type) {
      $utype = strtoupper($type);
      // Events of close relatives, e.g. _MARR_CHIL
      if (substr($fact->getTag(), 0, 6) == '_MARR_' && ($utype == 'CIVIL' || $utype == 'PARTNERS' || $utype == 'RELIGIOUS')) {
        // Translate MARR/TYPE using the code that supports MARR_CIVIL, etc. tags
        $type = GedcomTag::getLabel('MARR_' . $utype);
      } else {
        // Allow (custom) translations for other types
        $type = I18N::translate($type);
      }
      echo GedcomTag::getLabelValue('TYPE', e($type));
    }

    // Print the date of this fact/event
    echo FunctionsPrint::formatFactDate($fact, $record, true, true);

    // Print the place of this fact/event
    //[RC] adjusted
    //echo '<div class="place">', FunctionsPrint::formatFactPlace($fact, true, true, true), '</div>';

    $gve = $this->functionsPrint->formatFactPlace($fact, true, true, true);
    $script .= $gve->getScript();
    echo '<div class="place">', $gve->getMain(), '</div>';
    // A blank line between the primary attributes (value, date, place) and the secondary ones
    echo '<br>';

    $addr = $fact->attribute('ADDR');
    if ($addr !== '') {
      echo GedcomTag::getLabelValue('ADDR', $addr);
    }

    // Print the associates of this fact/event
    if ($fact->id() !== 'asso') {
      $assoRel = $this->formatAssociateRelationship($fact);
      echo $assoRel->getMain();
      $script .= $assoRel->getScript();
    }

    // Print any other "2 XXXX" attributes, in the order in which they appear.
    preg_match_all('/\n2 (' . Gedcom::REGEX_TAG . ') (.+)/', $fact->gedcom(), $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      switch ($match[1]) {
        case 'DATE':
        case 'TIME':
        case 'AGE':
        case 'PLAC':
        case 'ADDR':
        case 'ALIA':
        case 'ASSO':
        case '_ASSO':
        case 'DESC':
        case 'RELA':
        case 'STAT':
        case 'TEMP':
        case 'TYPE':
        case 'FAMS':
        case 'CONT':
          // These were already shown at the beginning
          break;
        case 'NOTE':
        case 'OBJE':
        case 'SOUR':
          // These will be shown at the end
          break;
        case '_UID':
        case 'RIN':
          // These don't belong at level 2, so do not display them.
          // They are only shown when editing.
          break;
        case 'EVEN': // 0 SOUR / 1 DATA / 2 EVEN / 3 DATE / 3 PLAC
          $events = [];
          foreach (preg_split('/ *, */', $match[2]) as $event) {
            $events[] = GedcomTag::getLabel($event);
          }
          if (count($events) == 1) {
            echo GedcomTag::getLabelValue('EVEN', $event);
          } else {
            echo GedcomTag::getLabelValue('EVEN', implode(I18N::$list_separator, $events));
          }
          if (preg_match('/\n3 DATE (.+)/', $fact->gedcom(), $date_match)) {
            $date = new Date($date_match[1]);
            echo GedcomTag::getLabelValue('DATE', $date->display());
          }
          if (preg_match('/\n3 PLAC (.+)/', $fact->gedcom(), $plac_match)) {
            echo GedcomTag::getLabelValue('PLAC', $plac_match[1]);
          }
          break;
        case 'FAMC': // 0 INDI / 1 ADOP / 2 FAMC / 3 ADOP
          $family = Family::getInstance(str_replace('@', '', $match[2]), $fact->record()->tree());
          if ($family) {
            echo GedcomTag::getLabelValue('FAM', '<a href="' . e($family->url()) . '">' . $family->fullName() . '</a>');
            if (preg_match('/\n3 ADOP (HUSB|WIFE|BOTH)/', $fact->gedcom(), $match)) {
              echo GedcomTag::getLabelValue('ADOP', GedcomCodeAdop::getValue($match[1], $label_person));
            }
          } else {
            echo GedcomTag::getLabelValue('FAM', '<span class="error">' . $match[2] . '</span>');
          }
          break;
        case '_WT_USER':
          $user = (new UserService())->findByIdentifier($match[2]); // may not exist
          if ($user) {
            echo GedcomTag::getLabelValue('_WT_USER', $user->realName());
          } else {
            echo GedcomTag::getLabelValue('_WT_USER', e($match[2]));
          }
          break;
        case 'RESN':
          switch ($match[2]) {
            case 'none':
              // Note: "2 RESN none" is not valid gedcom.
              // However, webtrees privacy rules will interpret it as "show an otherwise private fact to public".
              echo GedcomTag::getLabelValue('RESN', '<i class="icon-resn-none"></i> ' . I18N::translate('Show to visitors'));
              break;
            case 'privacy':
              echo GedcomTag::getLabelValue('RESN', '<i class="icon-resn-privacy"></i> ' . I18N::translate('Show to members'));
              break;
            case 'confidential':
              echo GedcomTag::getLabelValue('RESN', '<i class="icon-resn-confidential"></i> ' . I18N::translate('Show to managers'));
              break;
            case 'locked':
              echo GedcomTag::getLabelValue('RESN', '<i class="icon-resn-locked"></i> ' . I18N::translate('Only managers can edit'));
              break;
            default:
              echo GedcomTag::getLabelValue('RESN', e($match[2]));
              break;
          }
          break;
        case 'CALN':
          echo GedcomTag::getLabelValue('CALN', Filter::expandUrls($match[2], $record->tree()));
          break;
        case 'FORM': // 0 OBJE / 1 FILE / 2 FORM / 3 TYPE
          echo GedcomTag::getLabelValue('FORM', $match[2]);
          if (preg_match('/\n3 TYPE (.+)/', $fact->gedcom(), $type_match)) {
            echo GedcomTag::getLabelValue('TYPE', GedcomTag::getFileFormTypeValue($type_match[1]));
          }
          break;
        case 'URL':
        case '_URL':
        case 'WWW':
          $link = '<a href="' . e($match[2]) . '">' . e($match[2]) . '</a>';
          echo GedcomTag::getLabelValue($fact->getTag() . ':' . $match[1], $link);
          break;
        default:
          if ($fact->record()->tree()->getPreference('HIDE_GEDCOM_ERRORS') === '1' || GedcomTag::isTag($match[1])) {
            if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $match[2], $xmatch)) {
              // Links
              $linked_record = GedcomRecord::getInstance($xmatch[1], $fact->record()->tree());
              if ($linked_record) {
                $link = '<a href="' . e($linked_record->url()) . '">' . $linked_record->fullName() . '</a>';
                echo GedcomTag::getLabelValue($fact->getTag() . ':' . $match[1], $link);
              } else {
                echo GedcomTag::getLabelValue($fact->getTag() . ':' . $match[1], e($match[2]));
              }
            } else {
              // Non links
              echo GedcomTag::getLabelValue($fact->getTag() . ':' . $match[1], e($match[2]));
            }
          }
          break;
      }
    }
    echo FunctionsPrintFacts::printFactSources($tree, $fact->gedcom(), 2);
    echo FunctionsPrint::printFactNotes($tree, $fact->gedcom(), 2);
    FunctionsPrintFacts::printMediaLinks($tree, $fact->gedcom(), 2);
    echo '</td></tr>';

    return $script;
  }

  /**
   * Print the associations from the associated individuals in $event to the individuals in $record
   *
   * @param Fact $event
   *
   * @return GenericViewElement
   */
  protected function formatAssociateRelationship(Fact $event) {
    $parent = $event->record();
    // To whom is this record an assocate?
    if ($parent instanceof Individual) {
      // On an individual page, we just show links to the person
      $associates = [$parent];
    } elseif ($parent instanceof Family) {
      // On a family page, we show links to both spouses
      $associates = $parent->spouses();
    } else {
      // On other pages, it does not make sense to show associates
      return new GenericViewElement('', '');
    }

    preg_match_all('/^1 ASSO @(' . Gedcom::REGEX_XREF . ')@((\n[2-9].*)*)/', $event->gedcom(), $amatches1, PREG_SET_ORDER);
    preg_match_all('/\n2 _?ASSO @(' . Gedcom::REGEX_XREF . ')@((\n[3-9].*)*)/', $event->gedcom(), $amatches2, PREG_SET_ORDER);

    $html = '';
    $script = '';

    // For each ASSO record
    foreach (array_merge($amatches1, $amatches2) as $amatch) {
      $person = Individual::getInstance($amatch[1], $event->record()->tree());
      if ($person && $person->canShowName()) {
        // Is there a "RELA" tag
        if (preg_match('/\n[23] RELA (.+)/', $amatch[2], $rmatch)) {
          // Use the supplied relationship as a label
          $label = GedcomCodeRela::getValue($rmatch[1], $person);
        } else {
          // Use a default label
          $label = GedcomTag::getLabel('ASSO', $person);
        }

        $values = ['<a href="' . e($person->url()) . '">' . $person->fullName() . '</a>'];
        foreach ($associates as $associate) {
          $relationship_name = Functions::getCloseRelationshipName($associate, $person);
          if (!$relationship_name) {
            //$relationship_name = GedcomTag::getLabel('RELA');
            //[RC] adjusted
            $relationship_name = I18N::translate('No relationship found');
          }

          //[RC] adjusted
          $relationship_name_suffix = '';
          if ($parent instanceof Family) {
            // For family ASSO records (e.g. MARR), identify the spouse with a sex icon
            $sex = '<small>' . view('icons/sex-' . $associate->sex()) . '</small>';
            $relationship_name_suffix = $sex;
          }

          //[RC] adjusted
          $out = $this->getOutputForRelationship($event, $person, $associate, ' — ', $relationship_name, $relationship_name_suffix, false);
          if ($out != null) {
            $values[] = $out->getMain();
            $script .= $out->getScript();
          }

          //if ($parent instanceof Family) {
          //	// For family ASSO records (e.g. MARR), identify the spouse with a sex icon
          //	$sex = '<small>' . view('icons/sex-' . $associate->sex()) . '</small>';
          //$relationship_name .= $sex;
          //}
          //$values[] = '<a href="' . e(route('relationships', ['xref1' => $associate->xref(), 'xref2' => $person->xref(), 'ged' => $person->tree()->name()])) . '" rel="nofollow">' . $relationship_name . '</a>';
        }

        //[RC] adjusted
        //$value = implode(' — ', $values);
        $value = implode('', $values);

        // Use same markup as GedcomTag::getLabelValue()
        $asso = I18N::translate('<span class="label">%1$s:</span> <span class="field" dir="auto">%2$s</span>', $label, $value);
      } elseif (!$person && Auth::isEditor($event->record()->tree())) {
        $asso = GedcomTag::getLabelValue('ASSO', '<span class="error">' . $amatch[1] . '</span>');
      } else {
        $asso = '';
      }
      $html .= '<div class="fact_ASSO">' . $asso . '</div>';
    }

    return new GenericViewElement($html, $script);
  }

  //[RC] added
  //override hook, may return null
  protected function getOutputForRelationship(
          Fact $event,
          Individual $person,
          Individual $associate,
          $relationship_name_prefix,
          $relationship_name,
          $relationship_name_suffix,
          $inverse) {

    //TODO use $inverse here?
    $main = '<a href="' . e(route('relationships', ['xref1' => $associate->xref(), 'xref2' => $person->xref(), 'ged' => $person->tree()->name()])) . '" rel="nofollow">' . $relationship_name_prefix . $relationship_name . $relationship_name_suffix . '</a>';
    return new GenericViewElement($main, '');
  }
  
  //[RC] added
  //override hook
  protected function printAdditionalEditControls(Fact $event): GenericViewElement {
    return new GenericViewElement('', '');
  }
}
