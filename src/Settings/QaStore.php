<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Qa\Settings;

use Db;
use DbQuery;

/**
 * Pytania o produkty: odczyt i zapis.
 *
 * Jedna tabela, `quince_qa`. Pytanie należy do produktu I do sklepu, bo ten sam
 * produkt bywa w kilku sklepach z osobną obsługą — pytanie zadane w jednym nie
 * ma się pokazywać w drugim.
 *
 * Stany są trzy, nie dwa: czeka, opublikowane, odrzucone. Odrzucone zostaje
 * w tabeli, a nie znika, bo sklep bywa pytany drugi raz o to samo i chce
 * wiedzieć, że już to widział.
 */
final class QaStore
{
    public const CZEKA = 0;
    public const OPUBLIKOWANE = 1;
    public const ODRZUCONE = 2;

    private const TABELA = 'quince_qa';

    /**
     * Porcja pytań pokazywanych klientowi.
     *
     * Wychodzą tylko te opublikowane i tylko z odpowiedzią: pytanie bez
     * odpowiedzi na karcie produktu wygląda jak zignorowany klient.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forProduct(int $idProduct, int $idShop, int $ile, int $strona = 1): array
    {
        $strona = max(1, $strona);

        $sql = new DbQuery();
        $sql->select('id_quince_qa, customer_name, question, answer, answer_author, date_add, date_answer');
        $sql->from(self::TABELA);
        $sql->where('id_product = ' . $idProduct);
        $sql->where('id_shop = ' . $idShop);
        $sql->where('status = ' . self::OPUBLIKOWANE);
        $sql->where('answer IS NOT NULL');
        $sql->where("answer != ''");
        $sql->orderBy('date_answer DESC');
        $sql->limit($ile, ($strona - 1) * $ile);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
    }

    /** Ile pytań z odpowiedzią widzi klient przy tym produkcie. */
    public function countForProduct(int $idProduct, int $idShop): int
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from(self::TABELA);
        $sql->where('id_product = ' . $idProduct);
        $sql->where('id_shop = ' . $idShop);
        $sql->where('status = ' . self::OPUBLIKOWANE);
        $sql->where('answer IS NOT NULL');
        $sql->where("answer != ''");

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /** Zapis nowego pytania. Zwraca jego identyfikator albo zero. */
    public function add(array $pytanie): int
    {
        $ok = Db::getInstance()->insert(self::TABELA, $pytanie);

        return $ok ? (int) Db::getInstance()->Insert_ID() : 0;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from(self::TABELA);
        $sql->where('id_quince_qa = ' . $id);

        $wiersz = Db::getInstance()->getRow($sql);

        return $wiersz ?: null;
    }

    /**
     * Lista dla panelu: wybrany stan, najnowsze u góry.
     *
     * Szukanie obejmuje treść pytania, podpis klienta i nazwę produktu, bo po
     * tych trzech rzeczach sprzedawca pamięta sprawę — nie po numerze wiersza.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forAdmin(?int $status = null, string $szukaj = '', int $limit = 200): array
    {
        $sql = new DbQuery();
        $sql->select('q.*, pl.name AS product_name, s.name AS shop_name');
        $sql->from(self::TABELA, 'q');
        $sql->leftJoin('product_lang', 'pl', 'pl.id_product = q.id_product AND pl.id_shop = q.id_shop AND pl.id_lang = ' . (int) \Context::getContext()->language->id);
        $sql->leftJoin('shop', 's', 's.id_shop = q.id_shop');

        if ($status !== null) {
            $sql->where('q.status = ' . $status);
        }

        $szukaj = trim($szukaj);

        if ($szukaj !== '') {
            $fraza = pSQL($szukaj);
            $sql->where(
                "(q.question LIKE '%" . $fraza . "%'"
                . " OR q.customer_name LIKE '%" . $fraza . "%'"
                . " OR pl.name LIKE '%" . $fraza . "%')"
            );
        }

        $sql->orderBy('q.date_add DESC');
        $sql->limit($limit);

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /** Ile pytań w ogóle — liczba na wierzchu ekranu. */
    public function countAll(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . self::TABELA
        );
    }

    /** Ile pytań w danym stanie — liczby przy zakładkach panelu. */
    public function countByStatus(int $status): int
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from(self::TABELA);
        $sql->where('status = ' . $status);

        return (int) Db::getInstance()->getValue($sql);
    }

    /** Odpowiedź sklepu wraz z publikacją pytania. */
    public function answer(int $id, string $odpowiedz, string $autor): bool
    {
        return Db::getInstance()->update(
            self::TABELA,
            [
                'answer' => pSQL($odpowiedz),
                'answer_author' => pSQL($autor),
                'status' => self::OPUBLIKOWANE,
                'date_answer' => date('Y-m-d H:i:s'),
            ],
            'id_quince_qa = ' . $id,
            1
        );
    }

    public function setStatus(int $id, int $status): bool
    {
        return Db::getInstance()->update(
            self::TABELA,
            ['status' => $status],
            'id_quince_qa = ' . $id,
            1
        );
    }

    public function delete(int $id): bool
    {
        return Db::getInstance()->delete(self::TABELA, 'id_quince_qa = ' . $id, 1);
    }

    /** Tabela zakładana przy instalacji modułu. */
    public static function createTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABELA . '` (
                `id_quince_qa` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
                `customer_name` VARCHAR(64) NOT NULL,
                `customer_email` VARCHAR(255) NOT NULL,
                `question` TEXT NOT NULL,
                `answer` TEXT DEFAULT NULL,
                `answer_author` VARCHAR(64) DEFAULT NULL,
                `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_answer` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id_quince_qa`),
                KEY `karta_produktu` (`id_product`, `id_shop`, `status`),
                KEY `kolejka` (`status`, `date_add`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function dropTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABELA . '`'
        );
    }
}
