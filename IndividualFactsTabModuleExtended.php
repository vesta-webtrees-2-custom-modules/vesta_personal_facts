<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\GedcomCode\GedcomCodeRela_Ext;
use Cissee\WebtreesExt\Http\RequestHandlers\FunctionsPlaceProvidersAction;
use Cissee\WebtreesExt\Http\RequestHandlers\IndividualFactsTabExtenderProvidersAction;
use Cissee\WebtreesExt\Module\IndividualFactsTabModule_2x;
use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\RelationshipService;
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
use function app;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use function response;
use function route;
use function view;

class IndividualFactsTabModuleExtended extends IndividualFactsTabModule_2x implements 
    ModuleCustomInterface, 
    ModuleMetaInterface, 
    ModuleConfigInterface, 
    ModuleGlobalInterface, 
    ModuleTabInterface, 
    PrintFunctionsPlaceInterface {

    //must not use ModuleTabTrait here - already used in superclass IndividualFactsTabModule_2x,
    //and - more importantly - partially implemented there! (supportedFacts)
    use ModuleCustomTrait, ModuleMetaTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait {
        VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
        VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
        VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;    
        VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
        ModuleMetaTrait::customModuleVersion insteadof ModuleCustomTrait;
        ModuleMetaTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    }
  
    use IndividualFactsTabModuleTrait;
    use EmptyPrintFunctionsPlace;
          
    public function __construct(
            ModuleService $module_service, 
            LinkedRecordService $linked_record_service, 
            ClipboardService $clipboard_service) {
        
        parent::__construct($module_service, $linked_record_service, $clipboard_service);
    }

    //assumes to get called after setName!
    protected function getViewNameTab(): string {
        //we do not want to use the original name 'modules/personal_facts/tab' here, so we use our own namespace
        return $this->name() . '::tab';
        //return 'modules/personal_facts/tab';
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
        View::registerCustomView('::edit/add-fact-row', $this->name() . '::edit/add-fact-row');      
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

    protected function associateFacts(Individual $person): Collection {
        //shortcut?
        if (!$this->showAssociateFacts()) {
            return new Collection();
        }

        //Issue #7: adjust parent code: we want to display type of custom fact/event
        return parent::associateFacts($person);
    }

    protected function filterAssociateFact(Fact $fact) {
        [, $tag] = explode(':', $fact->tag());

        $restricted = boolval($this->getPreference('ASSO_RESTRICTED', '0'));
        if ($restricted) {
            $parent = $fact->record();
            if ($parent instanceof Family) {
                $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_FAM', 'MARR'), -1, PREG_SPLIT_NO_EMPTY);
                if (!in_array($tag, $restrictedTo, true)) {
                    return false;
                }
            } else {
                $restrictedTo = preg_split("/[, ;:]+/", $this->getPreference('ASSO_RESTRICTED_INDI', 'CHR,BAPM'), -1, PREG_SPLIT_NO_EMPTY);
                if (!in_array($tag, $restrictedTo, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    ////////////////////////////////////////////////////////////////////////////
  
    public function plac2html(PlaceStructure $ps): ?GenericViewElement {
    
        //probably not very efficient    
        return GenericViewElement::fromView(
            VestaUtils::vestaViewsNamespace() . '::fact-place', 
            ['ps' => $ps, 'module' => $this]);
    }

    public function map2html(MapCoordinates $map): ?GenericViewElement {
        return GenericViewElement::create($this->functionsFactPlace()->getMapLinks(
            $map->getLati(), 
            $map->getLong()));
    }
  
    ////////////////////////////////////////////////////////////////////////////
  
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
        return /* I18N: Module Configuration */I18N::translate('Modules listed here may provide additional data for facts and events (displayed in the configured order).');
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
                $response = $this->functionsVestals()->vestalBeforePlace($placeStructure);
                $responses[$response->classAttr()] = $response;
            } else if ('vestalAfterMap' == $method) {
                $response = $this->functionsVestals()->vestalAfterMap($placeStructure);
                $responses[$response->classAttr()] = $response;
            } else if ('vestalAfterNotes' == $method) {
                $response = $this->functionsVestals()->vestalAfterNotes($placeStructure);
                $responses[$response->classAttr()] = $response;
            } else if ('vestalMapCoordinates' == $method) {
                $response = $this->functionsVestals()->vestalMapCoordinates($placeStructure);
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
  
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    //functions called from custom view 'tab'

    public function getOutputBeforeTab(Individual $person) {
        $tree = $person->tree();
        $a1 = IndividualFactsTabExtenderUtils::accessibleModules($this, $tree, Auth::user())
                ->map(function (IndividualFactsTabExtenderInterface $module) use ($tree) {
                  return $module->hFactsTabRequiresModalVesta($tree);
                })
                ->toArray();
    
        $gve1 = GenericViewElement::createEmpty();
        if (!empty($a1)) {
            $script = implode($a1);
            $html = view(VestaUtils::vestaViewsNamespace() . '::modals/ajax-modal-vesta', [
                    'ajax' => true, //tab is loaded via ajax!
                    'select2Initializers' => [$script]
            ]);
    
            $gve1 = GenericViewElement::create($html);
        }        
    
        $a2 = IndividualFactsTabExtenderUtils::accessibleModules($this, $tree, Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
              return $module->hFactsTabGetOutputBeforeTab($person);
            })
            ->toArray();

        return GenericViewElement::implode([$gve1, GenericViewElement::implode($a2)]);
    }

    public function getOutputAfterTab(Individual $person) {
        $a = IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
              return $module->hFactsTabGetOutputAfterTab($person);
            })
            ->toArray();

        return GenericViewElement::implode($a);
    }
  
    public function getOutputInDescriptionBox(Individual $person) {
        return GenericViewElement::implode(IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
                            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
                              return $module->hFactsTabGetOutputInDBox($person);
                            })
                            ->toArray());
    }

    public function getOutputAfterDescriptionBox(Individual $person) {
        return GenericViewElement::implode(IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
                            ->map(function (IndividualFactsTabExtenderInterface $module) use ($person) {
                              return $module->hFactsTabGetOutputAfterDBox($person);
                            })
                            ->toArray());
    }
  
    public function functionsVestals():  FunctionsVestals {  
        return new FunctionsVestals(
                $this,
                $this->vestalsActionUrl(),
                $this->useVestals());
    }
  
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    //functions called from custom view 'fact'
  
    public function additionalStyles(Fact $fact): array {
    
        $styles = [];

        $additions = IndividualFactsTabExtenderUtils::accessibleModules($this, $fact->record()->tree(), Auth::user())
                ->map(function (IndividualFactsTabExtenderInterface $module) {
                  return $module->hFactsTabGetStyleadds();
                })
                ->toArray();

        foreach ($additions as $a) {
            foreach ($a as $id => $cssClass) {
                if ($fact->id() === $id) {
                    $styles[] = trim($cssClass);
                }
            }
        }
        return $styles;
    }
  
    public function gveAdditionalEditControls(Fact $fact): GenericViewElement {
        $additions = IndividualFactsTabExtenderUtils::accessibleModules($this, $fact->record()->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($fact) {
              return $module->hFactsTabGetAdditionalEditControls($fact);
            })
            ->toArray();
            
        return GenericViewElement::implode($additions);
    }
  
    public function gveLabelForAsso(
        string $label,
        Fact $fact,
        Individual $record): GenericViewElement {
    
        $main = '';
        $script = '';

        $parent = $fact->record();
        [, $tag] = explode(':', $fact->tag());

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
      
            $val = $this->getOutputForRelationship(
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
  
    public function getOutputForRelationship(
        Fact $event,
        Individual $person,
        Individual $associate,
        $relationship_name_prefix,
        $relationship_name,
        $relationship_name_suffix,
        $inverse): GenericViewElement {

        $outs = IndividualFactsTabExtenderUtils::accessibleModules($this, $person->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($event, $person, $associate, $relationship_name_prefix, $relationship_name, $relationship_name_suffix, $inverse) {
              return $module->hFactsTabGetOutputForAssoRel($event, $person, $associate, $relationship_name_prefix, $relationship_name, $relationship_name_suffix, $inverse);
            })
            ->toArray();

        foreach ($outs as $out) {
            if ($out == null) {
                //first return wins
                return null; //do not proceed
            }
            if (($out->getMain() !== '') || ($out->getScript() !== '')) {
                //first return wins
                return $out;
            }
        }

        //nothing hooked or only empty string(s) returned: fallback!
        return $this->getOutputForRelationshipFallback(
                    $event,
                    $person,
                    $associate,
                    $relationship_name_prefix,
                    $relationship_name,
                    $relationship_name_suffix,
                    $inverse);
    }
  
    protected function getOutputForRelationshipFallback(
        Fact $event,
        Individual $person,
        Individual $associate,
        $relationship_name_prefix,
        $relationship_name,
        $relationship_name_suffix,
        $inverse): GenericViewElement {

        //TODO use $inverse here?

        $main = "";

        $module = app(ModuleService::class)->findByComponent(ModuleChartInterface::class, $person->tree(), Auth::user())->first(static function (ModuleInterface $module) {
            return $module instanceof RelationshipsChartModule;
        });

        if ($module instanceof RelationshipsChartModule) {
            $main = '<a href="' . $module->chartUrl($associate, ['xref2' => $person->xref()]) . '" rel="nofollow">' . $relationship_name_prefix . $relationship_name . $relationship_name_suffix . '</a>';
        }

        //$main = '<a href="' . e(route('relationships', ['xref1' => $associate->xref(), 'xref2' => $person->xref(), 'ged' => $person->tree()->name()])) . '" rel="nofollow">' . $relationship_name_prefix . $relationship_name . $relationship_name_suffix . '</a>';

        //use the relationship name even if no chart is configured
        //(note: webtrees doesn't do this in fact-association-structure view)
        $main = $relationship_name_prefix . $relationship_name . $relationship_name_suffix;
        return new GenericViewElement($main, '');
    }
  
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    //functions called from custom view 'fact-place', and internally

    public function functionsFactPlace():  FunctionsFactPlace {  
        return new FunctionsFactPlace(
                  $this);
    }
  
}
