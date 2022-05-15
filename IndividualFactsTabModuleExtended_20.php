<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\Http\RequestHandlers\FunctionsPlaceProvidersAction;
use Cissee\WebtreesExt\Http\RequestHandlers\IndividualFactsTabExtenderProvidersAction;
use Cissee\WebtreesExt\Module\IndividualFactsTabModule_20;
use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\MoreI18N;
use Cissee\WebtreesExt\ToggleableFactsCategory;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vesta\CommonI18N;
use Vesta\Hook\HookInterfaces\EmptyPrintFunctionsPlace;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Vesta\Hook\HookInterfaces\PrintFunctionsPlaceInterface;
use Vesta\Model\GenericViewElement;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\VestalRequest;
use Vesta\VestaAdminController;
use Vesta\VestaModuleTrait;
use Vesta\VestaUtils;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use function response;
use function route;
use function view;

class IndividualFactsTabModuleExtended_20 extends IndividualFactsTabModule_20 implements 
  ModuleCustomInterface, 
  ModuleMetaInterface, 
  ModuleConfigInterface, 
  ModuleGlobalInterface, 
  ModuleTabInterface, 
  ModuleSidebarInterface,
  PrintFunctionsPlaceInterface {

  //must not use ModuleTabTrait here - already used in superclass IndividualFactsTabModule_2x,
  //and - more importantly - partially implemented there! (supportedFacts)
  use ModuleCustomTrait, ModuleMetaTrait, ModuleConfigTrait, ModuleGlobalTrait, ModuleSidebarTrait, VestaModuleTrait {
    VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
    VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
    VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;    
    VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
    ModuleMetaTrait::customModuleVersion insteadof ModuleCustomTrait;
    ModuleMetaTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
  }
  
  use IndividualFactsTabModuleTrait;
  use EmptyPrintFunctionsPlace;

  protected $functionsPrint;
  
  public function functionsPrint() {
    return $this->functionsPrint;
  }
          
  public function __construct(ModuleService $module_service, ClipboardService $clipboard_service) {
    parent::__construct($module_service, $clipboard_service);
    $this->functionsPrint = new FunctionsPrintWithHooks_20($this);
    $this->setFunctionsPrintFacts(new FunctionsPrintFactsWithHooks_20($this->functionsPrint, $this));
  }

  //assumes to get called after setName!
  protected function getViewName(): string {
    //we do not want to use the original name 'modules/relatives/tab' here, so we use our own namespace
    return $this->name() . '::tab_20';
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleMetaDatasJson(): string {
    return file_get_contents(__DIR__ . '/metadata.json');
  } 
  
  public function customModuleLatestMetaDatasJsonUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_personal_facts/master/metadata.json';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }

  public function onBoot(): void {
    $this->flashWhatsNew('\Cissee\Webtrees\Module\PersonalFacts\WhatsNew', 2);
    
    // Replace an existing view with our own version.
    View::registerCustomView('::edit/add-fact-row', $this->name() . '::edit/add-fact-row_20');      
  }
  
  public function tabTitle(): string {
    return $this->getTabTitle(MoreI18N::xlate('Facts and events'));
  }

  //there may be further ajax calls from this tab so we suggest to load tab itself via ajax
  public function canLoadAjax(): bool {
    return true; //must align with ajax-modal-vesta in getOutputBeforeTab()
  }
  
  public function assetsViaViews(): array {
    return [
        'css/webtrees.css' => 'css/webtrees'];
  }
  
  public function assetAdditionalHash(string $asset): string {
    //view is dynamic - we have to hash properly!
    $dataUri = $this->getPreference('CMP_1_ICON_DATA_URI', '');    
    return "CMP_1_ICON_DATA_URI:" . $dataUri . ";";
  }
  
  public function headContent(): string {
    $pre = '<link href="' . $this->assetUrl('css/style.css') . '" type="text/css" rel="stylesheet" />';

    //align with current theme (supporting the default webtrees themes, and specific custom themes)
    $themeName = Session::get('theme');
    if ('minimal' !== $themeName) {
      if ('fab' === $themeName) {
        //fab also uses font awesome icons
        $themeName = 'minimal';
      /*
      } else if ('_myartjaub_ruraltheme_' === $themeName) {
        //and the custom 'rural' theme - but not for map links!
        $themeName = 'minimal';
      } else if ('_jc-theme-justlight_' === $themeName) {
        //and the custom 'JustLight' theme - but not for map links!
        $themeName = 'minimal';
      } else if ('_jc-theme-justlight2_' === $themeName) {
        //and the custom 'JustLight' theme, version 2 - but not for map links!
        $themeName = 'minimal';
      */
      } else {
        //default
        $themeName = 'webtrees';
      }
    }
    
    $pre .= '<link href="' . $this->assetUrl('css/'.$themeName.'.css') . '" type="text/css" rel="stylesheet" />';
    
    return $pre;
  }
  
  protected function getOutputBeforeTab(GedcomRecord $record) {
    $tree = $record->tree();
    $a1 = IndividualFactsTabExtenderUtils::accessibleModules($this, $tree, Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($tree) {
              return $module->hFactsTabRequiresModalVesta($tree);
            })
            ->toArray();
    
    $gve1 = GenericViewElement::createEmpty();
    if (!empty($a1)) {
      $script = implode($a1);
      $html = view(VestaUtils::vestaViewsNamespace() . '::modals/ajax-modal-vesta_20', [
                'ajax' => true, //tab is NOW loaded via ajax!
                'select2Initializers' => [$script]
      ]);
    
      $gve1 = GenericViewElement::create($html);
    }        
    
    $a2 = IndividualFactsTabExtenderUtils::accessibleModules($this, $tree, Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($record) {
              return $module->hFactsTabGetOutputBeforeTab($record);
            })
            ->toArray();

    return GenericViewElement::implode([$gve1, GenericViewElement::implode($a2)]);
  }

  protected function getOutputAfterTab(GedcomRecord $record) {
    $a = IndividualFactsTabExtenderUtils::accessibleModules($this, $record->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($record) {
              return $module->hFactsTabGetOutputAfterTab($record, true);
            })
            ->toArray();

    return GenericViewElement::implode($a);
  }

  protected function additionalFacts(Individual $person) {
    $facts = array();
    $ret = IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
              return $module->hFactsTabGetAdditionalFacts($person);
            })
            ->toArray();

    foreach ($ret as $r) {
      foreach ($r as $rr) {
        $facts[] = $rr;
      }
    }
            
    return $facts;
  }

  protected function getToggleableFactsCategories(
      $show_relatives_facts, 
      $has_historical_facts) {
      
    $categories = [];

    /* [RC] note: this is problematic wrt asso events, which we still may want to show */
    if ($show_relatives_facts /*|| (!$this->getPreference('ASSO_SEPARATE', '0') && $this->showAssociateFacts())*/) {
      $categories[] = new ToggleableFactsCategory(
              'show-relatives-facts-pfh', //cf FunctionsPrintFactsWithHooks.additionalStyleadds()!
              '.wt-relation-fact-pfh',
              MoreI18N::xlate('Events of close relatives'));
    }

    if (/*$this->getPreference('ASSO_SEPARATE', '0') &&*/ $this->showAssociateFacts()) {
      $categories[] = new ToggleableFactsCategory(
              'show-associate-facts-pfh', //cf FunctionsPrintFactsWithHooks.additionalStyleadds()!
              '.wt-associate-fact-pfh',
              I18N::translate('Associated events'));
    } //if setting for separate checkbox isn't set: toggles via show-relatives-facts-pfh!

    if ($has_historical_facts) {
      $categories[] = new ToggleableFactsCategory(
              'show-historical-facts-pfh', //cf FunctionsPrintFactsWithHooks.additionalStyleadds()!
              '.wt-historic-fact-pfh',
              MoreI18N::xlate('Historic events'));
    }

    return $categories;
  }

  //[RC] ADDED
  protected function getOutputInDescriptionBox(Individual $person) {
    return GenericViewElement::implode(IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
                            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
                              return $module->hFactsTabGetOutputInDBox($person);
                            })
                            ->toArray());
  }

  //[RC] ADDED
  protected function getOutputAfterDescriptionBox(Individual $person) {
    return GenericViewElement::implode(IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
                            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
                              return $module->hFactsTabGetOutputAfterDBox($person);
                            })
                            ->toArray());
  }

  protected function showAssociateFacts() {
    $restricted = boolval($this->getPreference('ASSO_RESTRICTED', '0'));

    if ($restricted) {
      //check if completely empty - in which case we may shortcut			
      $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_INDI', 'CHR,BAPM'), -1, PREG_SPLIT_NO_EMPTY);
      if (sizeof($restrictedTo) === 0) {
        $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_FAM', 'MARR'), -1, PREG_SPLIT_NO_EMPTY);
        if (sizeof($restrictedTo) === 0) {
          return false;
        }
      }
    }

    return true;
  }

  protected function associateFacts(Individual $person): array {
    //shortcut?
    if (!$this->showAssociateFacts()) {
      return array();
    }

    //Issue #7: adjust parent code: we want to display type of custom fact/event
    return parent::associateFacts($person);
  }

  protected function filterAssociateFact(Fact $fact) {
    $restricted = boolval($this->getPreference('ASSO_RESTRICTED', '0'));
    if ($restricted) {
      $parent = $fact->record();
      if ($parent instanceof Family) {
        $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_FAM', 'MARR'), -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($fact->getTag(), $restrictedTo, true)) {
          return false;
        }
      } else {
        $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_INDI', 'CHR,BAPM'), -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($fact->getTag(), $restrictedTo, true)) {
          return false;
        }
      }
    }

    return true;
  }

  //////////////////////////////////////////////////////////////////////////////
  
  public function plac2html(PlaceStructure $ps): ?GenericViewElement {
    $fp = new FunctionsPrintWithHooks_20($this);
    return GenericViewElement::implode($fp->formatPlaceNameAndSubRecords($ps, false));
  }

  public function map2html(MapCoordinates $map): ?GenericViewElement {
    $fp = new FunctionsPrintWithHooks_20($this);
    return GenericViewElement::create($fp->getMapLinks($map->getLati(), $map->getLong()));
  }
  
  //////////////////////////////////////////////////////////////////////////////
  
  private function title1(): string {
    return CommonI18N::locationDataProviders();
  }
  
  private function description1(): string {
    return CommonI18N::mapCoordinates();
  }
  
  private function title2(): string {
    return /* I18N: Module Configuration */I18N::translate('Facts and Events Tab UI Element Providers');
  }
  
  private function description2(): string {
    return CommonI18N::factDataProvidersDescription();
  }
  
  //hook management - generalize?
  //adapted from ModuleController (e.g. listFooters)
  public function getFunctionsPlaceProvidersAction(): ResponseInterface {
    $modules = FunctionsPlaceUtils::modules($this, true);

    $controller = new VestaAdminController($this->name());
    return $controller->listHooks(
                    $modules,
                    FunctionsPlaceInterface::class,
                    $this->title1(),
                    $this->description1(),
                    true,
                    true);
  }
  
  public function getIndividualFactsTabExtenderProvidersAction(): ResponseInterface {
    $modules = IndividualFactsTabExtenderUtils::modules($this, true);

    $controller = new VestaAdminController($this->name());
    return $controller->listHooks(
                    $modules,
                    IndividualFactsTabExtenderInterface::class,
                    $this->title2(),
                    $this->description2(),
                    true,
                    true);
  }

  public function postFunctionsPlaceProvidersAction(ServerRequestInterface $request): ResponseInterface {
    $controller = new FunctionsPlaceProvidersAction($this);
    return $controller->handle($request);
  }
  
  public function postIndividualFactsTabExtenderProvidersAction(ServerRequestInterface $request): ResponseInterface {
    $controller = new IndividualFactsTabExtenderProvidersAction($this);
    return $controller->handle($request);
  }

  protected function editConfigBeforeFaq() {
    $modules1 = FunctionsPlaceUtils::modules($this, true);

    $url1 = route('module', [
        'module' => $this->name(),
        'action' => 'FunctionsPlaceProviders'
    ]);
    
    $modules2 = IndividualFactsTabExtenderUtils::modules($this, true);

    $url2 = route('module', [
        'module' => $this->name(),
        'action' => 'IndividualFactsTabExtenderProviders'
    ]);

    //cf control-panel.phtml
    ?>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-9">
                <ul class="fa-ul">
                    <li>
                        <span class="fa-li"><?= view('icons/block') ?></span>
                        <a href="<?= e($url1) ?>">
                            <?= $this->title1() ?>
                        </a>
                        <?= view('components/badge', ['count' => $modules1->count()]) ?>
                        <p class="small text-muted">
                          <?= $this->description1() ?>
                        </p>
                    </li>
                    <li>
                        <span class="fa-li"><?= view('icons/block') ?></span>
                        <a href="<?= e($url2) ?>">
                            <?= $this->title2() ?>
                        </a>
                        <?= view('components/badge', ['count' => $modules2->count()]) ?>
                        <p class="small text-muted">
                          <?= $this->description2() ?>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>		

    <?php
  }

  //////////////////////////////////////////////////////////////////////////////
  //ModuleSidebarInterface, cf IndividualMetadataModule
  
  // A list of facts that are handled by this module.
  protected const HANDLED_FACTS = [
      'AFN',
      'CHAN',
      'IDNO',
      'REFN',
      'RESN',
      'RFN',
      'RIN',
      'SSN',
      '_UID',
      
      //[RC] added
      '_FSFTID'
  ];

  /**
   * How should this module be identified in the control panel, etc.?
   *
   * @return string
   */
  public function sidebarTitle(): string
  {
      /* I18N: Name of a module/sidebar */
      return $this->getSidebarTitle(MoreI18N::xlate('Extra information'));
  }  
    
  /**
   * The default position for this sidebar.  It can be changed in the control panel.
   *
   * @return int
   */
  public function defaultSidebarOrder(): int
  {
      return 1;
  }

  /**
   * @param Individual $individual
   *
   * @return bool
   */
  public function hasSidebarContent(Individual $individual): bool
  {
      return $individual->facts(static::HANDLED_FACTS)->isNotEmpty();
  }

  /**
   * Load this sidebar synchronously.
   *
   * @param Individual $individual
   *
   * @return string
   */
  public function getSidebarContent(Individual $individual): string
  {
      ob_start();

      foreach ($individual->facts(static::HANDLED_FACTS) as $fact) {
          $this->functionsPrintFacts->printFact($fact, $individual);
      }

      $html = ob_get_clean();

      return strip_tags($html, '<a><div><span>');
  }

  /**
   * This module handles the following facts - so don't show them on the "Facts and events" tab.
   *
   * @return Collection<string>
   */
  public function supportedFacts(): Collection
  {      
      $tabFacts = parent::supportedFacts();
      $sidebarFacts = new Collection(static::HANDLED_FACTS);

      //fix for #45
      //this is for both tab and sidebar interface!
      //it would be better to have different methods here.
      //if the sidebar isn't visible though (via access level), we must modify the collection
      //because the method still gets called for ModuleTabInterface (and vice versa, theoretically)
      //we assume that this method is only ever called in these contexts (i.e. after accessLevel has been called), 
      //so we re-evaluate our specific visibilities
      //uargh this is hacky
      $tree = $this->treeUsedForAccessLevelCheck;
      
      if ($tree === null) {
        //unexpected, moving on ...
        return $tabFacts->merge($sidebarFacts);
      }
      
      $tabIsVisible = $this->accessLevel($tree, ModuleTabInterface::class) >= Auth::accessLevel($tree, Auth::user());
      $sidebarIsVisible = $this->accessLevel($tree, ModuleSidebarInterface::class) >= Auth::accessLevel($tree, Auth::user());
      
      if ($tabIsVisible && $sidebarIsVisible) {
        return $tabFacts->merge($sidebarFacts);
      }
      
      if ($tabIsVisible) {
        return $tabFacts;
      }
      
      if ($sidebarIsVisible) {
        return $sidebarFacts;
      }
      
      //why are we even here then? anyway:      
      return new Collection();
  }
  
  public function useVestals(): bool {    
    return true; //TODO via module setting?
  }
  
  public function vestalsActionUrl(): string {
    $parameters = [
        'module' => $this->name(),
        'action' => 'Vestals'
    ];

    $url = route('module', $parameters);
    
    return $url;
  }
  
  public function postVestalsAction(ServerRequestInterface $request): ResponseInterface {
        
    $body = json_decode($request->getBody());
    
    $responses = [];
    
    foreach ($body as $vestalRequestStd) {
      $method = VestalRequest::methodFromStd($vestalRequestStd);
      $placeStructure = PlaceStructure::fromStd($vestalRequestStd->args);
      
      if ('vestalBeforePlace' == $method) {
        $response = $this->functionsPrint()->vestalBeforePlace($placeStructure);
        $responses[$response->classAttr()] = $response;
      } else if ('vestalAfterMap' == $method) {
        $response = $this->functionsPrint()->vestalAfterMap($placeStructure);
        $responses[$response->classAttr()] = $response;
      } else if ('vestalAfterNotes' == $method) {
        $response = $this->functionsPrint()->vestalAfterNotes($placeStructure);
        $responses[$response->classAttr()] = $response;
      } else if ('vestalMapCoordinates' == $method) {
        $response = $this->functionsPrint()->vestalMapCoordinates($placeStructure);
        $responses[$response->classAttr()] = $response;
      } else {
        error_log("unexpected method:".$method);
      }
    }
    
    ob_start();
    //array_values required for sequential numeric indexes, otherwise we end up with json object
    echo json_encode(array_values($responses));
    return response(ob_get_clean());
  }
}
