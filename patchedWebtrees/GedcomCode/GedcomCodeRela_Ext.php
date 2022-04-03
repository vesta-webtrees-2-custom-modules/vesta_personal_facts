<?php

namespace Cissee\WebtreesExt\GedcomCode;

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
    //temp taken from GedcomCodeRela
    //in webtrees 2.1. apparently to be handled via RelationIsDescriptor, but that doesn't seem to be finished yet!
    //sex is always 'U'?
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

        switch ($type) {
            case 'attendant':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Attendant');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Attendant');
                }

                return I18N::translate('Attendant');

            case 'attending':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Attending');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Attending');
                }

                return I18N::translate('Attending');

            case 'best_man':
                // always male
                return I18N::translate('Best man');

            case 'bridesmaid':
                // always female
                return I18N::translate('Bridesmaid');

            case 'buyer':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Buyer');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Buyer');
                }

                return I18N::translate('Buyer');

            case 'circumciser':
                // always male
                return I18N::translate('Circumciser');

            case 'civil_registrar':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Civil registrar');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Civil registrar');
                }

                return I18N::translate('Civil registrar');

            case 'employee':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Employee');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Employee');
                }

                return I18N::translate('Employee');

            case 'employer':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Employer');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Employer');
                }

                return I18N::translate('Employer');

            case 'foster_child':
                // no sex implied
                return I18N::translate('Foster child');

            case 'foster_father':
                // always male
                return I18N::translate('Foster father');

            case 'foster_mother':
                // always female
                return I18N::translate('Foster mother');

            case 'friend':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Friend');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Friend');
                }

                return I18N::translate('Friend');

            case 'godfather':
                // always male
                return I18N::translate('Godfather');

            case 'godmother':
                // always female
                return I18N::translate('Godmother');

            case 'godparent':
                if ($sex === 'M') {
                    return I18N::translate('Godfather');
                }

                if ($sex === 'F') {
                    return I18N::translate('Godmother');
                }

                return I18N::translate('Godparent');

            case 'godson':
                // always male
                return I18N::translate('Godson');

            case 'goddaughter':
                // always female
                return I18N::translate('Goddaughter');

            case 'godchild':
                if ($sex === 'M') {
                    return I18N::translate('Godson');
                }

                if ($sex === 'F') {
                    return I18N::translate('Goddaughter');
                }

                return I18N::translate('Godchild');

            case 'guardian':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Guardian');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Guardian');
                }

                return I18N::translate('Guardian');

            case 'informant':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Informant');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Informant');
                }

                return I18N::translate('Informant');

            case 'lodger':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Lodger');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Lodger');
                }

                return I18N::translate('Lodger');

            case 'nanny':
                // no sex implied
                return I18N::translate('Nanny');

            case 'nurse':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Nurse');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Nurse');
                }

                return I18N::translate('Nurse');

            case 'owner':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Owner');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Owner');
                }

                return I18N::translate('Owner');

            case 'priest':
                // no sex implied
                return I18N::translate('Priest');

            case 'rabbi':
                // always male
                return I18N::translate('Rabbi');

            case 'registry_officer':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Registry officer');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Registry officer');
                }

                return I18N::translate('Registry officer');

            case 'seller':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Seller');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Seller');
                }

                return I18N::translate('Seller');

            case 'servant':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Servant');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Servant');
                }

                return I18N::translate('Servant');

            case 'slave':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Slave');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Slave');
                }

                return I18N::translate('Slave');

            case 'ward':
                if ($sex === 'M') {
                    return I18N::translateContext('MALE', 'Ward');
                }

                if ($sex === 'F') {
                    return I18N::translateContext('FEMALE', 'Ward');
                }

                return I18N::translate('Ward');

            case 'witness':
                // Do we need separate male/female translations for this?
                return I18N::translate('Witness');

            default:
                return I18N::translate($type);
        }
    }
}
