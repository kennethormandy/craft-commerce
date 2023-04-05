<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\Sale;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\commerce\records\PurchasableStore;
use craft\errors\SiteNotFoundException;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

/**
 * Base Purchasable
 *
 * @property string $description the element's title or any additional descriptive information
 * @property bool $isAvailable whether the purchasable is currently available for purchase
 * @property bool $isPromotable whether this purchasable can be subject to discounts or sales
 * @property bool $onPromotion whether this purchasable is currently on sale at a promotional price
 * @property float $promotionRelationSource The source for any promotion category relation
 * @property float $price the base price the item will be added to the line item with
 * @property-read float $salePrice the base price the item will be added to the line item with
 * @property-read string $priceAsCurrency the price
 * @property-read string $basePriceAsCurrency the base price
 * @property-read string $basePromotionalPriceAsCurrency the base promotional price
 * @property-read string $salePriceAsCurrency the base price the item will be added to the line item with
 * @property int $shippingCategoryId the purchasable's shipping category ID
 * @property string $sku a unique code as per the commerce_purchasables table
 * @property array $snapshot
 * @property bool $isShippable
 * @property bool $isTaxable
 * @property int $taxCategoryId the purchasable's tax category ID
 * @property-read Store $store
 * @property-read int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface
{
    /**
     * @var float|null
     */
    private ?float $_salePrice = null;

    /**
     * @var float|null
     * @see getPrice()
     * @see setPrice()
     */
    private ?float $_price = null;

    /**
     * The store based on the `siteId` of the instance of the purchasable.
     *
     * @var Store|null
     */
    private ?Store $_store = null;

    /**
     * @var float|null
     * @see getPromotionalPrice()
     * @see setPromotionalPrice()
     */
    private ?float $_promotionalPrice = null;

    /**
     * @var string SKU
     * @see getSku()
     * @see setSku()
     */
    private string $_sku = '';

    /**
     * @var int|null Tax category ID
     * @since 5.0.0
     */
    public ?int $taxCategoryId = null;

    /**
     * @var int|null Shipping category ID
     * @since 5.0.0
     */
    public ?int $shippingCategoryId = null;

    /**
     * @var float|null $width
     * @since 5.0.0
     */
    public ?float $width = null;

    /**
     * @var float|null $height
     * @since 5.0.0
     */
    public ?float $height = null;

    /**
     * @var float|null $length
     * @since 5.0.0
     */
    public ?float $length = null;

    /**
     * @var float|null $weight
     * @since 5.0.0
     */
    public ?float $weight = null;

    /**
     * @var float|null
     * @since 5.0.0
     */
    public ?float $basePrice = null;

    /**
     * @var float|null
     * @since 5.0.0
     */
    public ?float $basePromotionalPrice = null;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $freeShipping = false;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $promotable = false;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $availableForPurchase = true;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $minQty = null;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $maxQty = null;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $hasUnlimitedStock = false;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $stock = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'price';
        $names[] = 'promotionalPrice';
        $names[] = 'onPromotion';
        $names[] = 'salePrice';
        $names[] = 'sku';
        return $names;
    }

    /**
     * @inheritdoc
     * @since 3.2.9
     */
    public function fields(): array
    {
        $fields = parent::fields();

        $fields['salePrice'] = 'salePrice';
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();

        $names[] = 'description';
        $names[] = 'sales';
        $names[] = 'snapshot';
        return $names;
    }

    /**
     * @return array
     */
    public function currencyAttributes(): array
    {
        return [
            'basePrice',
            'basePromotionalPrice',
            'price',
            'promotionalPrice',
            'salePrice',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $classNameParts = explode('\\', static::class);

        return array_pop($classNameParts);
    }

    /**
     * @inheritdoc
     */
    public function getStore(): Store
    {
        if ($this->_store === null) {
            if ($this->siteId === null) {
                throw new InvalidConfigException('Purchasable::siteId cannot be null');
            }

            $this->_store = Plugin::getInstance()->getStores()->getStoreBySiteId($this->siteId);
            if ($this->_store === null) {
                throw new InvalidConfigException('Unable to retrieve store.');
            }
        }

        return $this->_store;
    }

    /**
     * @return int
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getStoreId(): int
    {
        return $this->getStore()->id;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getIsAvailable(): bool
    {
        if (!$this->availableForPurchase) {
            return false;
        }

        // is the element enabled?
        if ($this->getStatus() !== Element::STATUS_ENABLED) {
            return false;
        }

        if (!$this->hasUnlimitedStock && $this->stock < 1) {
            return false;
        }

        // Temporary SKU can not be added to the cart
        if (PurchasableHelper::isTempSku($this->getSku())) {
            return false;
        }

        return $this->stock >= 1 || $this->hasUnlimitedStock;
    }

    /**
     * @param float|null $price
     * @return void
     * @since 5.0.0
     */
    public function setPrice(?float $price): void
    {
        $this->_price = $price;
    }

    /**
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function getPrice(): ?float
    {
        return $this->_price ?? $this->basePrice;
    }

    /**
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     * @since 5.0.0
     */
    public function getPromotionalPrice(): ?float
    {
        $price = $this->getPrice();
        $promotionalPrice = $this->_promotionalPrice ?? $this->basePromotionalPrice;

        return ($promotionalPrice !== null && $promotionalPrice < $price) ? $promotionalPrice : null;
    }

    /**
     * @param float|null $price
     * @return void
     * @since 5.0.0
     */
    public function setPromotionalPrice(?float $price): void
    {
        $this->_promotionalPrice = $price;
    }

    /**
     * @inheritdoc
     */
    public function getSalePrice(): ?float
    {
        if ($this->_salePrice === null) {
            $this->_salePrice = $this->getPromotionalPrice() ?? $this->getPrice();
        }

        return $this->_salePrice ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->_sku ?? '';
    }

    /**
     * Returns the SKU as text but returns a blank string if it’s a temp SKU.
     */
    public function getSkuAsText(): string
    {
        $sku = $this->getSku();

        if (PurchasableHelper::isTempSku($sku)) {
            $sku = '';
        }

        return $sku;
    }

    /**
     * @param string|null $sku
     */
    public function setSku(string $sku = null): void
    {
        $this->_sku = $sku;
    }

    /**
     * Returns whether this variant has stock.
     */
    public function hasStock(): bool
    {
        return $this->stock > 0 || $this->hasUnlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategoryId ? Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId) : Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory();
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategory(): ShippingCategory
    {
        return $this->shippingCategoryId ?
            Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId, $this->getStore()->id)
            : Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($this->getStore()->id);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return (string)$this;
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem): void
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($order = $lineItem->getOrder()) && !$order->isCompleted) {
            if (($lineItem->qty > $this->stock) && !$this->hasUnlimitedStock) {
                $message = Craft::t('commerce', '{description} only has {stock} in stock.', ['description' => $lineItem->getDescription(), 'stock' => $this->stock]);
                /** @var OrderNotice $notice */
                $notice = Craft::createObject([
                    'class' => OrderNotice::class,
                    'attributes' => [
                        'type' => 'lineItemSalePriceChanged',
                        'attribute' => "lineItems.$lineItem->id.qty",
                        'message' => $message,
                    ],
                ]);
                $order->addNotice($notice);
                $lineItem->qty = $this->stock;
            }
        }

        $lineItem->weight = (float)$this->weight; //converting nulls
        $lineItem->height = (float)$this->height; //converting nulls
        $lineItem->length = (float)$this->length; //converting nulls
        $lineItem->width = (float)$this->width; //converting nulls
    }

    /**
     * @inheritdoc
     */
    public function getLineItemRules(LineItem $lineItem): array
    {
        $order = $lineItem->getOrder();

        // After the order is complete shouldn't check things like stock being available or the purchasable being around since they are irrelevant.
        if ($order && $order->isCompleted) {
            return [];
        }

        $lineItemQuantitiesById = [];
        $lineItemQuantitiesByPurchasableId = [];
        foreach ($order->getLineItems() as $item) {
            if ($item->id !== null) {
                $lineItemQuantitiesById[$item->id] = isset($lineItemQuantitiesById[$item->id]) ? $lineItemQuantitiesById[$item->id] + $item->qty : $item->qty;
            } else {
                $lineItemQuantitiesByPurchasableId[$item->purchasableId] = isset($lineItemQuantitiesByPurchasableId[$item->purchasableId]) ? $lineItemQuantitiesByPurchasableId[$item->purchasableId] + $item->qty : $item->qty;
            }
        }


        return [
            // an inline validator defined as an anonymous function
            [
                'purchasableId',
                function($attribute, $params, Validator $validator) use ($lineItem) {
                    $purchasable = $lineItem->getPurchasable();
                    if ($purchasable === null) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'No purchasable available.'));
                    }

                    if ($purchasable->getStatus() != Element::STATUS_ENABLED) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'The item is not enabled for sale.'));
                    }
                },
            ],
            [
                'qty',
                function($attribute, $params, Validator $validator) use ($lineItem, $lineItemQuantitiesById, $lineItemQuantitiesByPurchasableId) {
                    // @TODO change all attribute calls to pass in the the store from the order `$lineItem->getOrder()->getStore()`
                    if (!$this->hasStock()) {
                        $error = Craft::t('commerce', '“{description}” is currently out of stock.', ['description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    $lineItemQty = $lineItem->id !== null ? $lineItemQuantitiesById[$lineItem->id] : $lineItemQuantitiesByPurchasableId[$lineItem->purchasableId];

                    if ($this->hasStock() && !$this->hasUnlimitedStock && $lineItemQty > $this->stock) {
                        $error = Craft::t('commerce', 'There are only {num} “{description}” items left in stock.', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->minQty > 1 && $lineItemQty < $this->minQty) {
                        $error = Craft::t('commerce', 'Minimum order quantity for this item is {num}.', ['num' => $this->minQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->maxQty != 0 && $lineItemQty > $this->maxQty) {
                        $error = Craft::t('commerce', 'Maximum order quantity for this item is {num}.', ['num' => $this->maxQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }
                },
            ],
            [['qty'], 'integer', 'min' => 1, 'skipOnError' => false],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['sku'], 'string', 'max' => 255],
            [['sku', 'price'], 'required', 'on' => self::SCENARIO_LIVE],
            [['price', 'promotionalPrice', 'weight', 'width', 'length', 'height'], 'number'],
            [
                ['sku'],
                UniqueValidator::class,
                'targetClass' => PurchasableRecord::class,
                'caseInsensitive' => true,
                'on' => self::SCENARIO_LIVE,
            ],
            [
                ['stock'],
                'required',
                'when' => static function($model) {
                    /** @var Purchasable $model */
                    return !$model->hasUnlimitedStock;
                },
                'on' => self::SCENARIO_LIVE,
            ],
            [['stock'], 'number'],
            [['basePrice'], 'number'],
            [['basePromotionalPrice', 'minQty', 'maxQty'], 'number', 'skipOnEmpty' => true],
            [['freeShipping', 'hasUnlimitedStock', 'promotable', 'availableForPurchase'], 'boolean'],
            [['taxCategoryId', 'shippingCategoryId', 'price', 'promotionalPrice'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem): void
    {
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    public function getIsShippable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsTaxable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return $this->promotable;
    }

    /**
     * @inheritdoc
     */
    public function getPromotionRelationSource(): mixed
    {
        return $this->id;
    }

    /**
     * Update purchasable table
     *
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     */
    public function afterSave(bool $isNew): void
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        if (!$purchasable) {
            $purchasable = new PurchasableRecord();
        }

        $purchasable->sku = $this->getSku();
        $purchasable->id = $this->id;
        $purchasable->width = $this->width;
        $purchasable->height = $this->height;
        $purchasable->length = $this->length;
        $purchasable->weight = $this->weight;
        $purchasable->taxCategoryId = $this->taxCategoryId;
        $purchasable->shippingCategoryId = $this->shippingCategoryId;

        // Only update the description for the primary site until we have a concept
        // of an order having a site ID
        if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
            $purchasable->description = $this->getDescription();
        }

        $purchasable->save(false);

        // Set purchasables stores data
        if ($purchasable->id) {
            $purchasableStoreRecord = PurchasableStore::findOne([
                'purchasableId' => $this->id,
                'storeId' => $this->getStoreId(),
            ]);
            if (!$purchasableStoreRecord) {
                $purchasableStoreRecord = Craft::createObject(PurchasableStore::class);
                $purchasableStoreRecord->storeId = $this->getStore()->id;
            }

            $purchasableStoreRecord->basePrice = $this->basePrice;
            $purchasableStoreRecord->basePromotionalPrice = $this->basePromotionalPrice;
            $purchasableStoreRecord->stock = $this->stock;
            $purchasableStoreRecord->hasUnlimitedStock = $this->hasUnlimitedStock;
            $purchasableStoreRecord->minQty = $this->minQty;
            $purchasableStoreRecord->maxQty = $this->maxQty;
            $purchasableStoreRecord->promotable = $this->promotable;
            $purchasableStoreRecord->availableForPurchase = $this->availableForPurchase;
            $purchasableStoreRecord->freeShipping = $this->freeShipping;
            $purchasableStoreRecord->purchasableId = $this->id;

            $purchasableStoreRecord->save(false);
        }

        parent::afterSave($isNew);
    }

    /**
     * Clean up purchasable table
     */
    public function afterDelete(): void
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        $purchasable?->delete();

        parent::afterDelete();
    }

    /**
     * @return Sale[] The sales that relate directly to this purchasable
     * @throws InvalidConfigException
     */
    public function relatedSales(): array
    {
        return Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($this);
    }

    /**
     * @inheritdoc
     */
    public function getOnPromotion(): bool
    {
        return $this->getPromotionalPrice() !== null;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        return array_merge($labels, ['sku' => 'SKU']);
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $html = parent::metaFieldsHtml($static);

        $html .= Cp::selectFieldHtml([
            'id' => 'tax-category',
            'name' => 'taxCategoryId',
            'label' => Craft::t('commerce', 'Tax Category'),
            'options' => Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList(),
            'value' => $this->taxCategoryId,
        ]);

        $html .= Cp::selectFieldHtml([
            'id' => 'shipping-category',
            'name' => 'shippingCategoryId',
            'label' => Craft::t('commerce', 'Shipping Category'),
            'options' => Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList($this->getStore()->id),
            'value' => $this->shippingCategoryId,
        ]);

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'sku' => Html::encode($this->getSkuAsText()),
            'price' => $this->basePrice, // @TODO change this to the `asCurrency` attribute when implemented
            'promotionalPrice' => $this->basePromotionalPrice, // @TODO change this to the `asCurrency` attribute when implemented
            'weight' => $this->weight !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits : '',
            'length' => $this->length !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'width' => $this->width !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'height' => $this->height !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            default => parent::tableAttributeHtml($attribute),
        };
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
            'price' => Craft::t('commerce', 'Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Quantities'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'sku',
            'price',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [...parent::defineSearchableAttributes(), ...[
            'description',
            'sku',
            'price',
            'width',
            'height',
            'length',
            'weight',
            'stock',
            'hasUnlimitedStock',
            'minQty',
            'maxQty',
        ]];
    }
}
