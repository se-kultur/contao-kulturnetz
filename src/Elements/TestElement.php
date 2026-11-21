<?php
namespace SeKultur\ContaoKulturnetzBundle\Elements;

use Contao\BackendTemplate;
use Contao\ContentElement;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;

//use SeKultur\ContaoKulturnetzBundle\ArtistModel;

class TestElement extends ContentElement
{
    protected $strTemplate = 'ce_test';

    /**
     * Displays a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (RequestContext::isBackendRequest()) {
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = '### '.mb_strtoupper($GLOBALS['TL_LANG']['CTE']['testelement'][0]).' ###';

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
