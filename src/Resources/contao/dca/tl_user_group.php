<?php

use Contao\Backend;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
        ->addLegend('alpdeskfee_legend', 'elements_legend', PaletteManipulator::POSITION_BEFORE, true)
        ->addField('alpdesk_fee_elements', 'alpdeskfee_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', 'tl_user_group');

$GLOBALS['TL_DCA']['tl_user_group']['fields']['alpdesk_fee_elements'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_user_group']['alpdesk_fee_elements'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => ['tl_user_group_alpdeskfee', 'getContentElements'],
    'reference' => &$GLOBALS['TL_LANG']['CTE'],
    'eval' => array('multiple' => true, 'helpwizard' => true),
    'sql' => "blob NULL"
];

class tl_user_group_alpdeskfee extends Backend {

  public function getContentElements() {
    return array_map('array_keys', $GLOBALS['TL_CTE']);
  }

}