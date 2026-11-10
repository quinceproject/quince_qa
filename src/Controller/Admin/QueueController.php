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

use Configuration;
use Language;
use Mail;
use Quince\Qa\Settings\QaSettings;
use Quince\Qa\Settings\QaStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Validate;

/**
 * Kolejka pytań do odpowiedzi.
 *
 * Osobny widok od ustawień, a nie druga część tego samego ekranu: pytań bywa
 * kilkaset, więc ustawienia pod listą byłyby poza zasięgiem. Tu sprzedawca
 * wchodzi codziennie, tam raz.
 */
class QueueController extends QaBaseController
{
    public function __construct(
        private readonly QaSettings $settings,
        private readonly QaStore $store
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $stan = $request->query->get('status');
        $stan = ($stan === null || $stan === '') ? null : (int) $stan;
        $szukaj = trim((string) $request->query->get('search', ''));

        return $this->renderPanel('@Modules/quince_qa/views/templates/admin/queue.html.twig', [
            'rows' => $this->store->forAdmin($stan, $szukaj),
            'status' => $stan,
            'search' => $szukaj,
            'stats' => [
                'all' => $this->store->countAll(),
                'waiting' => $this->store->countByStatus(QaStore::CZEKA),
                'published' => $this->store->countByStatus(QaStore::OPUBLIKOWANE),
                'rejected' => $this->store->countByStatus(QaStore::ODRZUCONE),
            ],
        ] + $this->chrome('quince_qa'));
    }

    /** Odpowiedź sklepu. Publikuje pytanie i, jeśli sklep tak ustawił, zawiadamia klienta. */
    public function answerAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_qa_answer', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_qa');
        }

        $id = (int) $request->request->get('id');
        $odpowiedz = trim((string) $request->request->get('answer', ''));

        if ($id < 1 || $odpowiedz === '') {
            $this->addFlash('error', $this->trans('Write an answer before publishing it.', self::DOMENA));

            return $this->redirectToRoute('quince_qa');
        }

        $autor = trim($this->settings->get('ANSWER_AUTHOR'));

        if ($autor === '') {
            $autor = (string) Configuration::get('PS_SHOP_NAME');
        }

        $this->store->answer($id, $odpowiedz, $autor);

        if ($this->settings->flag('NOTIFY_CUSTOMER')) {
            $this->zawiadomKlienta($id, $odpowiedz);
        }

        $this->addFlash('success', $this->trans('The answer is published on the product page.', self::DOMENA));

        return $this->redirectToRoute('quince_qa');
    }

    /** Odrzucenie, przywrócenie do kolejki albo usunięcie pytania. */
    public function statusAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_qa_status', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_qa');
        }

        $id = (int) $request->request->get('id');
        $co = (string) $request->request->get('do');

        if ($id < 1) {
            return $this->redirectToRoute('quince_qa');
        }

        if ($co === 'delete') {
            $this->store->delete($id);
            $this->addFlash('success', $this->trans('The question is deleted.', self::DOMENA));

            return $this->redirectToRoute('quince_qa');
        }

        $stany = [
            'reject' => QaStore::ODRZUCONE,
            'wait' => QaStore::CZEKA,
        ];

        if (isset($stany[$co])) {
            $this->store->setStatus($id, $stany[$co]);
        }

        return $this->redirectToRoute('quince_qa');
    }

    /**
     * Wiadomość do klienta o odpowiedzi.
     *
     * Temat i treść są ustawieniem sklepu, bo to jego głos. Pusty temat
     * zostawia wiadomość bez wysyłki: lepszy brak maila niż mail z pustym
     * tematem.
     */
    private function zawiadomKlienta(int $id, string $odpowiedz): void
    {
        $pytanie = $this->store->find($id);

        if ($pytanie === null || !Validate::isEmail((string) $pytanie['customer_email'])) {
            return;
        }

        $idLang = (int) $this->getContext()->language->id;
        $temat = trim($this->settings->text('EMAIL_SUBJECT', $idLang));

        if ($temat === '') {
            return;
        }

        $nazwaSklepu = (string) Configuration::get('PS_SHOP_NAME');

        Mail::Send(
            $this->jezykSzablonu($idLang),
            'quince_qa_answer',
            str_replace('{shop_name}', $nazwaSklepu, $temat),
            [
                '{shop_name}' => $nazwaSklepu,
                '{customer_name}' => (string) $pytanie['customer_name'],
                '{question}' => nl2br(htmlspecialchars((string) $pytanie['question'], ENT_QUOTES, 'UTF-8')),
                '{answer}' => nl2br(htmlspecialchars($odpowiedz, ENT_QUOTES, 'UTF-8')),
                '{intro}' => $this->settings->text('EMAIL_BODY', $idLang),
            ],
            (string) $pytanie['customer_email'],
            (string) $pytanie['customer_name'],
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'quince_qa/mails/',
            false,
            (int) $pytanie['id_shop']
        );
    }

    /**
     * Język, w którym mamy szablon wiadomości.
     *
     * Brakującego szablonu PrestaShop niczym nie zastępuje: wiadomość po prostu
     * nie wychodzi, a w dzienniku sklepu zostaje „Brakuje szablonu e-mail".
     */
    private function jezykSzablonu(int $idLang): int
    {
        $katalog = _PS_MODULE_DIR_ . 'quince_qa/mails/';
        $iso = (string) Language::getIsoById($idLang);

        if ($iso !== '' && is_file($katalog . $iso . '/quince_qa_answer.html')) {
            return $idLang;
        }

        foreach (Language::getLanguages(false) as $jezyk) {
            if (is_file($katalog . $jezyk['iso_code'] . '/quince_qa_answer.html')) {
                return (int) $jezyk['id_lang'];
            }
        }

        return $idLang;
    }
}
