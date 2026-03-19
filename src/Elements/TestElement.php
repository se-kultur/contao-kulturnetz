<?php
namespace SeKultur\ContaoKulturnetzBundle\Elements;

//use SeKultur\ContaoKulturnetzBundle\ArtistModel;

class TestElement extends \ContentElement
{
    protected $strTemplate = 'ce_test';

    /**
     * Displays a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE') {
            $template = new \BackendTemplate('be_wildcard');
            $template->wildcard = '### '.utf8_strtoupper($GLOBALS['TL_LANG']['CTE']['testelement'][0]).' ###';

            return $template->parse();
        }

        return parent::generate();
    }

    /**
     * Generates the content element.
     */
    protected function compile()
    {
        // Do something
		//$objArtists = \SeKultur\ContaoKulturnetzBundle\ArtistModel::findAll(); 

    }
}