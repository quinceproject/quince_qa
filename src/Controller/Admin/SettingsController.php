<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Qa\Controller\Admin;

use Contact;
use Quince\Qa\Settings\QaSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Validate;

/**
 * Ustawienia modułu pytań.
 *
 * Osobny widok od kolejki: ustawienia zmienia się raz, pytania czyta codziennie,
 * a przy kilkuset pytaniach ustawienia pod listą byłyby poza zasięgiem.
 */
class SettingsController extends QaBaseController
{
    public function __construct(private readonly QaSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_qa/views/templates/admin/settings.html.twig', [
            'settings' => $this->settings->plain(),
            'texts' => $this->settings->translated(),
            'languages' => $this->languages(),
            'contacts' => $this->kontakty(),
        ] + $this->chrome('quince_qa_settings'));
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_qa_settings_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_qa_settings');
        }

        $this->settings->save(
            [
                // Przełącznik przysyła „0" z ukrytego pola, gdy jest wyłączony,
                // więc brak wartości i zero znaczą to samo.
                'ALLOW_GUESTS' => $request->request->get('ALLOW_GUESTS') ? '1' : '0',
                'MODERATE' => $request->request->get('MODERATE') ? '1' : '0',
                'NOTIFY_SHOP' => $request->request->get('NOTIFY_SHOP') ? '1' : '0',
                'NOTIFY_CUSTOMER' => $request->request->get('NOTIFY_CUSTOMER') ? '1' : '0',
                'PER_PAGE' => (string) max(1, (int) $request->request->get('PER_PAGE', 5)),
                'ANSWER_AUTHOR' => trim((string) $request->request->get('ANSWER_AUTHOR', '')),
                // Pola wyboru przychodzą jako tablica; zapisujemy sam ciąg
                // identyfikatorów, żeby ustawienie zostało jednym napisem.
                'COPY_CONTACTS' => implode(',', array_filter(array_map(
                    'intval',
                    (array) $request->request->all('COPY_CONTACTS')
                ))),
            ],
            [
                'DRAWER_INTRO' => $this->wielojezyczne($request, 'DRAWER_INTRO'),
                'EMAIL_SUBJECT' => $this->wielojezyczne($request, 'EMAIL_SUBJECT'),
                'EMAIL_BODY' => $this->wielojezyczne($request, 'EMAIL_BODY'),
            ]
        );

        $this->addFlash('success', $this->trans('Settings saved.', self::DOMENA));

        return $this->redirectToRoute('quince_qa_settings');
    }

    /**
     * Pole wielojęzyczne przychodzi jako osobne pole na język, z przyrostkiem
     * `_<id języka>` w nazwie. Tak wypisuje je makro panelu.
     *
     * @return array<int,string>
     */
    private function wielojezyczne(Request $request, string $klucz): array
    {
        $out = [];

        foreach ($this->languages() as $jezyk) {
            $idLang = (int) $jezyk['id_lang'];
            $out[$idLang] = trim((string) $request->request->get($klucz . '_' . $idLang, ''));
        }

        return $out;
    }

    /**
     * Kontakty sklepu pod pole kopii powiadomień.
     *
     * @return array<int,array{id:int,name:string,email:string}>
     */
    private function kontakty(): array
    {
        $out = [];

        foreach (Contact::getContacts((int) $this->getContext()->language->id) as $kontakt) {
            if (!Validate::isEmail($kontakt['email'])) {
                continue;
            }

            $out[] = [
                'id' => (int) $kontakt['id_contact'],
                'name' => (string) $kontakt['name'],
                'email' => (string) $kontakt['email'],
            ];
        }

        return $out;
    }
}
