<?php

namespace SeKultur\ContaoKulturnetzBundle\Module;

use Contao\BackendTemplate;
use Contao\Input;
use Contao\Module;
use SeKultur\ContaoKulturnetzBundle\Helper\RequestContext;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;

class ModuleHostEdit extends Module
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_host_edit';

	/**
	 * Do not display the module if there are no menu items
	 *
	 * @return string
	 */
	public function generate()
	{
		if (RequestContext::isBackendRequest())
		{
			/** @var BackendTemplate|object $objTemplate */
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### Veranstalter:innenprofil bearbeiten ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao?do=themes&table=tl_module&act=edit&id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Generate module
	 */
	protected function compile()
	{
		$memberId = RequestContext::getMemberId();

		// Ohne eigenes Profil bleibt das Formular leer. Ein leeres Model liefert
		// beim Lesen der Felder null, statt PHP-Warnungen zu erzeugen.
		$this->Template->host = new HostsModel();

		if ($memberId > 0) {
			$alias = Input::get('auto_item');
			// Ohne Alias gibt es kein Profil; findByIdOrAlias() erwartet in Contao 5.7 einen String.
			$host = (null !== $alias && '' !== $alias) ? HostsModel::findByIdOrAlias($alias) : null;

			if ($host?->member_id == $memberId) {
				$this->Template->host = $host;
			} else {
				// REDIRECT
			}
		} else {
			// REDIRECT LOGIN
		}
	}
}