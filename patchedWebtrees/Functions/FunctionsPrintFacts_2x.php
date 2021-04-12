<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\WebtreesExt\GedcomCode\GedcomCodeRela_Ext;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
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
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Submission;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\View;
use Vesta\Model\GenericViewElement;
use function app;
use function view;

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
    if ($fact->getTag() === 'EVEN' && $fact->value() === 'CLOSE_RELATIVE') {
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

  public function printFact(Fact $fact, GedcomRecord $record): void {
    $script = $this->printFactAndReturnScript($fact, $record);
    View::push('javascript');
    echo $script;
    View::endpush();
  }
  
  

  /**
   * Print a fact record, for the individual/family/source/repository/etc. pages.
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
    $tag = $fact->getTag();
    $label = $fact->label();
    $value = $fact->value();
    $type = $fact->attribute('TYPE');
    $id = $fact->id();

    $element = Registry::elementFactory()->make($fact->tag());

    // Some facts don't get printed here ...
    switch ($tag) {
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
        if ($tree->getPreference('HIDE_GEDCOM_ERRORS') === '0' && !GedcomTag::isTag($tag)) {
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
      $styleadd = 'wt-new';
    }
    if ($fact->isPendingDeletion()) {
      $styleadd = 'wt-old';
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
        if ($utype === 'CIVIL' || $utype === 'PARTNERS' || $utype === 'RELIGIOUS') {
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
        //[RC] adjusted
        $this->children[$fact->record()->xref()] = true;
        /* I18N: Abbreviation for "number %s" */
        $label .= '<br>' . I18N::translate('#%s', I18N::number(count($this->children)));
        break;
      case '_BIRT_GCHI':
      case '_BIRT_GCH1':
      case '_BIRT_GCH2':
        $this->grandchildren[$fact->record()->xref()] = true;
        /* I18N: Abbreviation for "number %s" */
        $label .= '<br>' . I18N::translate('#%s', I18N::number(count($this->grandchildren)));
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
    if (($fact->id() != 'histo') && ($fact->id() !== 'asso') && ($fact->canEdit() || ($additionalControls->getMain() != ''))) {
      echo '<div class="editfacts nowrap">';

      if (($fact->id() != 'histo') && ($fact->id() !== 'asso') && $fact->canEdit()) {
        echo view('edit/icon-fact-edit', ['fact' => $fact, 'url' => $record->url()]);
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
            $finalLabel = GedcomCodeRela_Ext::getValueOrNullForMARR($rela, $record);
            break;
          case 'CHR':
            if ($inverted !== null) {
              $finalLabel = GedcomCodeRela_Ext::getValueOrNullForCHR($inverted, $record);
            }
            break;
          case 'BAPM':
            if ($inverted !== null) {
              $finalLabel = GedcomCodeRela_Ext::getValueOrNullForBAPM($inverted, $record);
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
          } else {
            echo ": " . MoreI18N::xlate('Associate');
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
            $sex = '<small>' . view('icons/sex', ['sex' => $label_person->sex()]) . '</small>';
            $suffix = $sex . ')';
          }
          $val = $this->getOutputForRelationship($fact, $label_person, $record, '(', $relationship_name, $suffix, true);
          if ($val != null) {
            echo "<br/>";
            echo $val->getMain();
            $script .= $val->getScript();
          }
        }
      }

      if ($main !== '') {
        echo '<div class="editfacts nowrap">';
        echo $main;
        echo '</div>';
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
    switch ($tag) {
      case 'ADDR':
      case 'AFN':
      case 'LANG':
      case 'PUBL':
      case 'RESN':
          echo '<div class="field">' . $element->value($value, $tree) . '</div>';
          break;
      case '_FSFTID':
        echo '<div class="field"><a href="https://www.familysearch.org/tree/person/details/', rawurlencode($value), '">', e($value), '</a></div>';
        break;

      case 'ASSO':
        // we handle this later, in format_asso_rela_record()
        break;
      case 'EMAIL':
      case 'EMAI':
      case '_EMAIL':
        echo '<div class="field"><a href="mailto:', e($value), '">', e($value), '</a></div>';
        break;
      case 'REPO':
        $repository = $fact->target();
        if ($repository instanceof Repository) {
          echo '<div><a class="field" href="', e($repository->url()), '">', $repository->fullName(), '</a></div>';
        } else {
          echo '<div class="error">', e($value), '</div>';
        }
        break;
      case 'SUBM':
        $submitter = $fact->target();
        if ($submitter instanceof Submitter) {
          echo '<div><a class="field" href="', e($submitter->url()), '">', $submitter->fullName(), '</a></div>';
        } else {
          echo '<div class="error">', e($value), '</div>';
        }
        break;
      case 'SUBN':
        $submission = $fact->target();
        if ($submission instanceof Submission) {
          echo '<div><a class="field" href="', e($submission->url()), '">', $submission->fullName(), '</a></div>';
        } else {
          echo '<div class="error">', e($value), '</div>';
        }
        break;
      case 'URL':
      case '_URL':
      case 'WWW':
        echo '<div class="field"><a href="', e($value), '">', e($value), '</a></div>';
        break;
      case 'TEXT': // 0 SOUR / 1 TEXT
        echo Filter::formatText($value, $tree);
        break;
      case '_GOV':
        echo '<div class="field"><a href="https://gov.genealogy.net/item/show/', e($value), '">', e($value), '</a></div>';
        break;
      default:
        // Display the value for all other facts/events
        switch ($value) {
          case '':
          case 'CLOSE_RELATIVE':
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
            if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $value, $match)) {
              $target = $fact->target();
              if ($target instanceof GedcomRecord) {
                echo '<div><a href="', e($target->url()), '">', $target->fullName(), '</a></div>';
              } else {
                echo '<div class="error">', e($value), '</div>';
              }
            } else {
              echo '<div class="field"><span dir="auto">', e($value), '</span></div>';
            }
            break;
        }
        break;
    }

    // Print the type of this fact/event
    if ($type !== '' && $tag !== 'EVEN' && $tag !== 'FACT') {
      // Allow (custom) translations for other types
      $type = I18N::translate($type);
      echo GedcomTag::getLabelValue('TYPE', e($type));
    }

    // Print the date of this fact/event
    //[RC] quick fix for webtrees #3532
    echo FunctionsPrint_2x::formatFactDate($fact, $record, true, true, ($fact->id() === 'asso'));

    // Print the place of this fact/event
    //[RC] adjusted
    //echo '<div class="place">', FunctionsPrint::formatFactPlace($fact, true, true, true), '</div>';

    $gve = $this->functionsPrint->formatFactPlace($fact, true, true, true);
    $script .= $gve->getScript();
    echo '<div class="place">', $gve->getMain(), '</div>';
    //[RC] adjusted: unnecessary if there isn't anything to follow!
    // A blank line between the primary attributes (value, date, place) and the secondary ones
    //echo '<br>';
    ob_start();

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
          echo GedcomTag::getLabelValue('EVEN', implode(I18N::$list_separator, $events));

          if (preg_match('/\n3 DATE (.+)/', $evenMatches[$currentEvenMatch][0], $date_match)) {
            $date = new Date($date_match[1]);
            echo GedcomTag::getLabelValue('DATE', $date->display());
          }
          if (preg_match('/\n3 PLAC (.+)/', $evenMatches[$currentEvenMatch][0], $plac_match)) {
            echo GedcomTag::getLabelValue('PLAC', $plac_match[1]);
          }
          $currentEvenMatch++;

          break;
        case 'FAMC': // 0 INDI / 1 ADOP / 2 FAMC / 3 ADOP
          $family = Registry::familyFactory()->make(str_replace('@', '', $match[2]), $tree);
          if ($family instanceof Family) {
            echo GedcomTag::getLabelValue('FAM', '<a href="' . e($family->url()) . '">' . $family->fullName() . '</a>');
            if (preg_match('/\n3 ADOP (HUSB|WIFE|BOTH)/', $fact->gedcom(), $adop_match)) {
              echo GedcomTag::getLabelValue('ADOP', GedcomCodeAdop::getValue($adop_match[1]));
            }
          } else {
            echo GedcomTag::getLabelValue('FAM', '<span class="error">' . $match[2] . '</span>');
          }
          break;
        case '_WT_USER':
          if (Auth::check()) {
            $user = (new UserService())->findByIdentifier($match[2]); // may not exist
            if ($user instanceof UserInterface) {
              echo GedcomTag::getLabelValue('_WT_USER', '<span dir="auto">' . e($user->realName()) . '</span>');
            } else {
              echo GedcomTag::getLabelValue('_WT_USER', e($match[2]));
            }
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
          echo GedcomTag::getLabelValue('CALN', Filter::expandUrls($match[2], $tree));
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
          echo GedcomTag::getLabelValue($tag . ':' . $match[1], $link);
          break;
        default:
          if ($tree->getPreference('HIDE_GEDCOM_ERRORS') === '1' || GedcomTag::isTag($match[1])) {
            if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $match[2], $xmatch)) {
              // Links
              $linked_record = Registry::gedcomRecordFactory()->make($xmatch[1], $tree);
              if ($linked_record) {
                $link = '<a href="' . e($linked_record->url()) . '">' . $linked_record->fullName() . '</a>';
                echo GedcomTag::getLabelValue($tag . ':' . $match[1], $link);
              } else {
                echo GedcomTag::getLabelValue($tag . ':' . $match[1], e($match[2]));
              }
            } else {
              // Non links
              echo GedcomTag::getLabelValue($tag . ':' . $match[1], e($match[2]));
            }
          }
          break;
      }
    }
    echo FunctionsPrintFacts::printFactSources($tree, $fact->gedcom(), 2);
    echo FunctionsPrint::printFactNotes($tree, $fact->gedcom(), 2);
    FunctionsPrintFacts::printMediaLinks($tree, $fact->gedcom(), 2);

    //[RC] adjusted
    $html = ob_get_clean();
    if ($html !== '') {
      // A blank line between the primary attributes (value, date, place) and the secondary ones
      echo '<br>';
      echo $html;
    }

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
      $person = Registry::individualFactory()->make($amatch[1], $event->record()->tree());
      if ($person && $person->canShowName()) {        
        // Is there a "RELA" tag
        if (preg_match('/\n([23]) RELA (.+)/', $amatch[2], $rmatch)) {
            if ($rmatch[1] === '2') {
                $base_tag = $event->record()->tag();
            } else {
                $base_tag = $event->tag();
            }
            // Use the supplied relationship as a label
            $label = Registry::elementFactory()->make($base_tag . ':_ASSO:RELA')->value($rmatch[2], $parent->tree());
        } elseif (preg_match('/^1 _?ASSO/', $event->gedcom())) {
            // Use a default label
            $label = Registry::elementFactory()->make($event->tag())->label();
        } else {
            // Use a default label
            $label = Registry::elementFactory()->make($event->tag() . ':_ASSO')->label();
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
            $sex = '<small>' . view('icons/sex', ['sex' => $associate->sex()]) . '</small>';
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
          //	$sex = '<small>' . view('icons/sex', ['sex' => $associate->sex()]) . '</small>';
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
    $main = "";
    
    $module = app(ModuleService::class)->findByComponent(ModuleChartInterface::class, $person->tree(), Auth::user())->first(static function (ModuleInterface $module) {
      return $module instanceof RelationshipsChartModule;
    });
        
    if ($module instanceof RelationshipsChartModule) {
      $main = '<a href="' . $module->chartUrl($associate, ['xref2' => $person->xref()]) . '" rel="nofollow">' . $relationship_name_prefix . $relationship_name . $relationship_name_suffix . '</a>';
    }
    
    //$main = '<a href="' . e(route('relationships', ['xref1' => $associate->xref(), 'xref2' => $person->xref(), 'ged' => $person->tree()->name()])) . '" rel="nofollow">' . $relationship_name_prefix . $relationship_name . $relationship_name_suffix . '</a>';
    
    return new GenericViewElement($main, '');
  }

  //[RC] added
  //override hook
  protected function printAdditionalEditControls(Fact $event): GenericViewElement {
    return new GenericViewElement('', '');
  }

}
