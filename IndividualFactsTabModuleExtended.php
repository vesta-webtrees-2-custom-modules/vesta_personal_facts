<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\Http\RequestHandlers\FunctionsPlaceProvidersAction;
use Cissee\WebtreesExt\Http\RequestHandlers\IndividualFactsTabExtenderProvidersAction;
use Cissee\WebtreesExt\Module\IndividualFactsTabModule_2x;
use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\Module\ModuleVestalInterface;
use Cissee\WebtreesExt\Module\ModuleVestalTrait;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleMapLinkInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
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
use Vesta\VestaAdminController;
use Vesta\VestaModuleTrait;
use Vesta\VestaUtils;
use function app;
use function route;
use function view;

class IndividualFactsTabModuleExtended extends IndividualFactsTabModule_2x implements 
    ModuleCustomInterface, 
    ModuleMetaInterface, 
    ModuleConfigInterface, 
    ModuleGlobalInterface, 
    ModuleTabInterface, 
    ModuleMapLinkInterface,
    ModuleVestalInterface,
    PrintFunctionsPlaceInterface {

    //must not use ModuleTabTrait here - already used in superclass IndividualFactsTabModule_2x,
    //and - more importantly - partially implemented there! (supportedFacts)
    
    //skip ModuleMapLinkTrait here - it doesn't contain anyting that's useful for us
    use ModuleCustomTrait, ModuleMetaTrait, ModuleConfigTrait, ModuleGlobalTrait, ModuleVestalTrait, VestaModuleTrait {
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
            ClipboardService $clipboard_service,
            IndividualFactsService $individual_facts_service,
            ModuleService $module_service,            
            LinkedRecordService $linked_record_service) {
        
        parent::__construct($clipboard_service, $individual_facts_service, $module_service, $linked_record_service);
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

        //explicitly register in order to re-use in views where we cannot pass via variable
        //(could also resolve via module service)
        app()->instance(IndividualFactsTabModuleExtended::class, $this); //do not use bind()! for some reason leads to 'Illegal offset type in isset or empty'

        // Replace an existing view with our own version.    
        View::registerCustomView('::edit/add-fact-row', $this->name() . '::edit/add-fact-row');

        //TODO make this configurable?
        View::registerCustomView('::family-page', $this->name() . '::family-page');
        
        $this->flashWhatsNew('\Cissee\Webtrees\Module\PersonalFacts\WhatsNew', 2);
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
  


    protected function additionalFacts(Individual $record) {
        
        $facts = array();
        $ret = IndividualFactsTabExtenderUtils::accessibleModules($this, $record->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($record) {
              return $module->hFactsTabGetAdditionalFacts($record);
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
        $tag = explode(':', $fact->tag())[1];

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
            VestaUtils::vestaViewsNamespace() . '::fact-place', [   
                'ps' => $ps, 
                'module' => $this,
                'hideCoordinates' => boolval($this->getPreference('LINKS_AFTER_PLAC', '0'))]);
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
    
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////
   
    //ModuleVestalInterface
    public function hideCoordinates(): bool {
        return boolval($this->getPreference('LINKS_AFTER_PLAC', '0'));
    }
   
    //ModuleMapLinkInterface
    public function mapLink(Fact $fact): string {
        return $this->functionsFactPlace()->mapLink($fact);
    }
    
    //functions called from custom view 'fact-place', and internally

    public function functionsFactPlace():  FunctionsFactPlace {  
        return new FunctionsFactPlace($this);
    }
  
}
