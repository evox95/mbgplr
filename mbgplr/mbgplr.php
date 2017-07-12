<?php
/**
 * 2017 Mateusz Bartocha
 * 
 * @author      Mateusz Bartocha <contact@bestcoding.net>
 * @copyright   2017 Mateusz Bartocha
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @www         https://bestcoding.net
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MBGPLR extends Module {

    /**
     * Constructor
     */
    public function __construct() {
        $this->name = 'mbgplr';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Mateusz Bartocha bestcoding.net';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Generate product link rewrites');
        $this->description = $this->l('This module generates link rewrites for all products');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Get configuration page
     *
     * @return string
     */
    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name) && (int) Tools::getValue('MBGPLR_run') == 1) {
            if ($this->genLinkRewritesForAllProducts()) {
                $output .= $this->displayConfirmation($this->l('Link rewrites generated successfully'));
            } else {
                $output .= $this->displayConfirmation($this->l('Something went wrong, please try again'));
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Prepare configuration form
     *
     * @return string
     */
    public function displayForm() {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form = array(
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('Settings'),
                        'icon' => 'icon-cog'
                    ),
                    'input' => array(
                        array(
                            'type' => 'switch',
                            'label' => $this->l('Generate new link rewrites for your all products'),
                            'name' => 'MBGPLR_run',
                            'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Yes')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('No')
                                )
                            ),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Run'),
                        'class' => 'btn btn-default pull-right'
                    ),
                )
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array();
        
        $helper->fields_value['MBGPLR_run'] = 0;

        return $helper->generateForm($fields_form);
    }

    /**
     * Generate link rewrites for all products
     * 
     * @return bool
     */
    protected function genLinkRewritesForAllProducts() {

        $q = "
            DROP PROCEDURE IF EXISTS removeSpecialChars;

            CREATE PROCEDURE removeSpecialChars ( )

            BEGIN

                DECLARE i INT;

                SET i = 0;

                label: WHILE (i <= 255) DO

                    SET i = i + 1;

                    IF i >= 48 AND i <= 57 THEN
                        ITERATE label;
                    END IF;

                    IF i >= 65 AND i <= 90 THEN
                        UPDATE `" . _DB_PREFIX_ . "product_lang` SET `link_rewrite` = SUBSTRING(replace(`link_rewrite`, char(i), char(i+32)), 1, 128);
                        ITERATE label;
                    END IF;

                    IF i >= 97 AND i <= 122 THEN
                        ITERATE label;
                    END IF;

                    UPDATE `" . _DB_PREFIX_ . "product_lang` SET `link_rewrite` = SUBSTRING(replace(`link_rewrite`, char(i), '-'), 1, 128);

                END WHILE label;

                SET i = 1;
                    label: WHILE (i > 0) DO
                        UPDATE `" . _DB_PREFIX_ . "product_lang` SET `link_rewrite` = SUBSTRING(`link_rewrite`, 1, CHAR_LENGTH(`link_rewrite`)-1) WHERE `link_rewrite` LIKE '%-';
                        UPDATE `" . _DB_PREFIX_ . "product_lang` SET `link_rewrite` = SUBSTRING(`link_rewrite`, 2, CHAR_LENGTH(`link_rewrite`)-1) WHERE `link_rewrite` LIKE '-%';
                        SELECT IF(EXISTS(SELECT `id_product` FROM `" . _DB_PREFIX_ . "product_lang` WHERE `link_rewrite` LIKE '%-' OR `link_rewrite` LIKE '-%'  LIMIT 1), 1, 0) INTO i; 
                END WHILE label;

            END; 

            UPDATE `" . _DB_PREFIX_ . "product_lang` SET `link_rewrite` = SUBSTRING(`name`, 1, 128);

            CALL removeSpecialChars();
            
            DROP PROCEDURE IF EXISTS removeSpecialChars;
        ";

        return Db::getInstance()->execute($q);
    }

}
