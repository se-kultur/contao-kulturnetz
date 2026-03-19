<?php
// src/EventListener/PrepareFormDataListener.php
namespace SeKultur\ContaoKulturnetzBundle\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\CoreBundle\Slug\Slug;
use Contao\Form;
use Doctrine\DBAL\Connection;

#[AsHook('prepareFormData')]
class PrepareFormDataListener
{
    // Change these variables for your form, calendar and author
    private const FORM_ID = 3;
    private const CALENDAR_ID = 5;
    private const AUTHOR_ID = 1;

    private $slug;
    private $db;

    public function __construct(Slug $slug, Connection $db)
    {
        $this->slug = $slug;
        $this->db = $db;
    }

    public function __invoke(array &$submittedData, array $labels, array $fields, Form $form): void
    {
		dd('da bin ich');

        // Check if this is the right form
        if (self::FORM_ID !== (int) $form->id) {
            return;
        }

        // Mandatory fields
        $submittedData['pid'] = self::CALENDAR_ID;
        $submittedData['author'] = self::AUTHOR_ID;

        // Set this to false, if newly created events should not be immediately published
        $submittedData['published'] = true;

        // Generate unique alias
        $submittedData['alias'] = $this->getSlug($submittedData['title']);

        // Convert and set date fields
        $submittedData['startDate'] = strtotime(trim($submittedData['startDate'])) ?: null;
        $submittedData['startTime'] = $submittedData['startDate'];

        // Optional fields
        if (!empty(trim($submittedData['endDate']))) {
            $submittedData['endDate'] = strtotime(trim($submittedData['endDate'])) ?: null;
            $submittedData['endTime'] = $submittedData['endDate'];
        } else {
            $submittedData['endDate'] = null;
            $submittedData['endTime'] = null;
        }
    }

    private function getSlug(string $text, string $locale = 'de', string $validChars = '0-9a-z'): string
    {
        $options = [
            'locale' => $locale,
            'validChars' => $validChars,
        ];
        
        $duplicateCheck = function (string $slug): bool {
            return $this->db->fetchOne('SELECT COUNT(*) FROM tl_calendar_events WHERE alias = ?', [$slug]) > 0;
        };

        return $this->slug->generate($text, $options, $duplicateCheck);
    }
}