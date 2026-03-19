<?php

namespace SeKultur\ContaoKulturnetzBundle\EventListener;

use Contao\Form;
use SeKultur\ContaoKulturnetzBundle\Model\ArtistModel;

class SaveArtistListener
{
    public function __invoke(array $data, Form $form): array
    {
		
		var_dump('TEST');
       /* if ($form->targetTable !== ArtistModel::getTable()) {
            return $data;
        }

        if (!$data['first_name'] && $data['firstname']) {
            $data['first_name'] = $data['firstname'];
        }

        if (!$data['last_name'] && $data['lastname']) {
            $data['last_name'] = $data['lastname'];
        }

        if (!$data['email'] && $data['e-mail']) {
            $data['email'] = $data['e-mail'];
        }

        $formData = [
            'pid' => $form->id,
            'tstamp' => $data['tstamp'],
            'alias' => $data['alias'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ];

        $serialized = array_diff_assoc(
            $data,
            $formData
        );

        $formData['form_data'] = serialize($serialized);

        return $formData;*/
		return true;
    }
}