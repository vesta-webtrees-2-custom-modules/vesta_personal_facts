<?php

namespace Cissee\WebtreesExt\GedcomCode;

use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;

class GedcomCodeRela_Ext {

  public static function getValueOrNullForMARR($type, GedcomRecord $record = null) {
    if ($record instanceof Individual) {
      $sex = $record->sex();
    } else {
      $sex = 'U';
    }

    switch ($type) {
      case 'best_man':
        // always male
        return I18N::translate('Best man at a marriage');
      case 'bridesmaid':
        // always female
        return I18N::translate('Bridesmaid at a marriage');
      case 'witness':
        switch ($sex) {
          case 'M':
            return I18N::translateContext('MALE', 'Witness at a marriage');
          case 'F':
            return I18N::translateContext('FEMALE', 'Witness at a marriage');
          default:
            return I18N::translate('Witness at a marriage');
        }
      default:
        switch ($sex) {
          case 'M':
            return I18N::translateContext('MALE', 'Associate at a marriage');
          case 'F':
            return I18N::translateContext('FEMALE', 'Associate at a marriage');
          default:
            return I18N::translate('Associate at a marriage');
        }
    }
  }

  public static function getValueOrNullForBAPM($type, GedcomRecord $record = null) {
    if ($record instanceof Individual) {
      $sex = $record->sex();
    } else {
      $sex = 'U';
    }

    switch ($type) {
      case 'godson':
        // always male
        return I18N::translate('Baptism of a godson');
      case 'goddaughter':
        // always female
        return I18N::translate('Baptism of a goddaughter');
      case 'godchild':
        switch ($sex) {
          case 'M':
            return I18N::translate('Baptism of a godson');
          case 'F':
            return I18N::translate('Baptism of a goddaughter');
          default:
            return I18N::translate('Baptism of a godchild');
        }
      default:
        return null;
    }
  }

  public static function getValueOrNullForCHR($type, GedcomRecord $record = null) {
    if ($record instanceof Individual) {
      $sex = $record->sex();
    } else {
      $sex = 'U';
    }

    switch ($type) {
      case 'godson':
        // always male
        return I18N::translate('Christening of a godson');
      case 'goddaughter':
        // always female
        return I18N::translate('Christening of a goddaughter');
      case 'godchild':
        switch ($sex) {
          case 'M':
            return I18N::translate('Christening of a godson');
          case 'F':
            return I18N::translate('Christening of a goddaughter');
          default:
            return I18N::translate('Christening of a godchild');
        }
      default:
        return null;
    }
  }

  public static function invert($type) {
    switch ($type) {
      case 'attendant':
        return 'attending';
      case 'attending':
        return 'attendant';
      case 'best_man':
        return null;
      case 'bridesmaid':
        return null;
      case 'buyer':
        return 'seller';
      case 'circumciser':
        return null;
      case 'civil_registrar':
        return null;
      case 'employee':
        return 'employer';
      case 'employer':
        return 'employee';
      case 'foster_child':
        return null; //meh webtrees - why not use 'foster_parent'?
      case 'foster_father':
        return 'foster_child';
      case 'foster_mother':
        return 'foster_child';
      case 'friend':
        return 'friend'; //symmetric
      case 'godfather':
        return 'godchild';
      case 'godmother':
        return 'godchild';
      case 'godparent':
        return 'godchild';
      case 'godson':
        return 'godparent';
      case 'goddaughter':
        return 'godparent';
      case 'godchild':
        return 'godparent';
      case 'guardian':
        return 'ward';
      case 'informant':
        return null;
      case 'lodger':
        return null;
      case 'nanny':
        return null;
      case 'nurse':
        return null;
      case 'owner':
        return 'slave';
      case 'priest':
        return null;
      case 'rabbi':
        return null;
      case 'registry_officer':
        return null;
      case 'seller':
        return 'buyer';
      case 'servant':
        return null;
      case 'slave':
        return 'owner';
      case 'ward':
        return 'guardian';
      case 'witness':
        return null;
      default:
        return null;
    }
  }
  
    //(Webtrees::VERSION, '2.1')  
    /**
     * Translate a code, for an (optional) record.
     * We need the record to translate the sex (godfather/godmother) but
     * we won’t have this when adding data for new individuals.
     *
     * @param string            $type
     * @param GedcomRecord|null $record
     *
     * @return string
     */
    public static function getValue(string $type, GedcomRecord $record = null): string
    {
        if ($record instanceof Individual) {
            $sex = $record->sex();
        } else {
            $sex = 'U';
        }

        $descriptor = new RelationIsDescriptor('label');
        $values = $descriptor->values($sex);

        if (array_key_exists($type, $values)) {
            return $values[$type];
        }
        
        return $type;
    }
}
