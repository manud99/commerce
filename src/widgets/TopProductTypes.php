<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\stats\TopProductTypes as TopProductTypesStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\assets\admintable\AdminTableAsset;
use DateTime;

/**
 * Top Product Types widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopProductTypes extends Widget
{
    /**
     * @var int|DateTime|null
     */
    public mixed $startDate = null;

    /**
     * @var int|DateTime|null
     */
    public mixed $endDate = null;

    /**
     * @var string|null
     */
    public ?string $dateRange = null;

    /**
     * @var string|null Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    /**
     * @var TopProductTypesStat
     */
    private TopProductTypesStat $_stat;

    /**
     * @var string
     */
    private string $_title;

    /**
     * @var array
     */
    private array $_typeOptions;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->_typeOptions = [
            'qty' => Craft::t('commerce', 'Qty'),
            'revenue' => Craft::t('commerce', 'Revenue'),
        ];

        $this->_title = match ($this->type) {
            'revenue' => Craft::t('commerce', 'Top Product Types by Revenue'),
            'qty' => Craft::t('commerce', 'Top Product Types by Qty Sold'),
            default => Craft::t('commerce', 'Top Product Types'),
        };

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TopProductTypesStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopProductTypesStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true)
        );

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Top Product Types');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return $this->_stat->getDateRangeWording();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $stats = $this->_stat->get();

        if (empty($stats)) {
            return Html::tag('p', Craft::t('commerce', 'No stats available.'), ['class' => 'zilch']);
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);
        $view->registerAssetBundle(AdminTableAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/producttypes/top/body', [
            'stats' => $stats,
            'type' => $this->type,
            'typeLabel' => $this->_typeOptions[$this->type] ?? '',
            'id' => 'top-products' . StringHelper::randomString(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'top-products' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/producttypes/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
        ]);
    }
}
