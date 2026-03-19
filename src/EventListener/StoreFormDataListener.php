<?php
// src/EventListener/StoreFormDataListener.php
namespace SeKultur\ContaoKulturnetzBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;

#[AsHook('storeFormData')]
class StoreFormDataListener
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Security
     */
    private $security;

    public function __construct(Connection $connection, Security $security)
    {
        $this->connection = $connection;
        $this->security = $security;
    }

    public function __invoke(array $data, Form $form): array
    {
        /*$data['member'] = 0;

        $user = $this->security->getUser();
       
        if (!$user instanceof FrontendUser) {
            return $data;
        }   

        if (!$this->columnExistsInTable('member', $form->targetTable)) {
            return $data;
        }

        // Also store the member ID who submitted the form
        $data['member'] = $user->id;

        return $data;*/
		var_dump('yolo?');
    }
}