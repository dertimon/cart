<?php

declare(strict_types=1);

namespace Extcode\Cart\Domain\Model\Cart;

/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

final class BeVariant implements AdditionalDataInterface, BeVariantInterface
{
    use AdditionalDataTrait;

    protected string $titleDelimiter = ' - ';

    protected string $skuDelimiter = '-';

    protected ?float $specialPrice = null;

    protected array $beVariants = [];

    protected float $gross = 0.0;

    protected float $net = 0.0;

    protected float $tax = 0.0;

    protected bool $isFeVariant = false;

    protected int $min = 0;

    protected int $max = 0;

    protected int $stock = 0;

    public function __construct(
        protected string $id,
        protected BeVariantInterface|ProductInterface $parent,
        protected string $title,
        protected string $sku,
        protected int $priceCalcMethod,
        protected float $price,
        protected int $quantity = 0
    ) {
        $this->reCalc();
    }

    public function toArray(): array
    {
        $variantArr = [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'price_calc_method' => $this->priceCalcMethod,
            'price' => $this->getPrice(),
            'specialPrice' => $this->getSpecialPrice(),
            'taxClass' => $this->getTaxClass(),
            'quantity' => $this->quantity,
            'price_total_gross' => $this->gross,
            'price_total_net' => $this->net,
            'tax' => $this->tax,
            'additionals' => $this->getAdditionals(),
        ];

        if ($this->beVariants) {
            $innerVariantArr = [];

            foreach ($this->beVariants as $variant) {
                $innerVariantArr[] = [$variant->getId() => $variant->toArray()];
            }

            $variantArr[] = ['variants' => $innerVariantArr];
        }

        return $variantArr;
    }

    public function getParent(): BeVariantInterface|ProductInterface
    {
        return $this->parent;
    }

    public function setParent(BeVariantInterface|ProductInterface $parent): void
    {
        $this->parent = $parent;
    }

    public function getProduct(): ProductInterface
    {
        if ($this->parent instanceof ProductInterface) {
            return $this->parent;
        }

        return $this->parent->getProduct();
    }

    public function isNetPrice(): bool
    {
        return $this->parent->isNetPrice();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitleDelimiter(): string
    {
        return $this->titleDelimiter;
    }

    public function setTitleDelimiter(string $titleDelimiter): void
    {
        $this->titleDelimiter = $titleDelimiter;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCompleteTitle(): string
    {
        $title = '';

        if ($this->parent instanceof BeVariantInterface) {
            $title = $this->parent->getCompleteTitle();
        } elseif ($this->parent instanceof ProductInterface) {
            $title = $this->parent->getTitle();
        }

        if ($this->isFeVariant) {
            $title .= $this->titleDelimiter . $this->id;
        } else {
            $title .= $this->titleDelimiter . $this->title;
        }

        return $title;
    }

    public function getCompleteTitleWithoutProduct(): string
    {
        $title = '';
        $titleDelimiter = '';

        if ($this->parent instanceof BeVariantInterface) {
            $title = $this->parent->getCompleteTitleWithoutProduct();
            $titleDelimiter = $this->titleDelimiter;
        }

        if ($this->isFeVariant) {
            $title .= $titleDelimiter . $this->id;
        } else {
            $title .= $titleDelimiter . $this->title;
        }

        return $title;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getSpecialPrice(): ?float
    {
        return $this->specialPrice;
    }

    public function setSpecialPrice(float $specialPrice): void
    {
        $this->specialPrice = $specialPrice;
    }

    /**
     * Returns Best Price (min of Price and Special Price)
     */
    public function getBestPrice(): float
    {
        $bestPrice = $this->price;

        if ($this->specialPrice &&
            (
                (($this->specialPrice < $bestPrice) && in_array($this->priceCalcMethod, [0, 1, 4, 5])) ||
                (($this->specialPrice > $bestPrice) && in_array($this->priceCalcMethod, [2, 3]))
            )
        ) {
            $bestPrice = $this->specialPrice;
        }

        return $bestPrice;
    }

    public function getDiscount(): float
    {
        return $this->getPriceCalculated() - $this->getBestPriceCalculated();
    }

    public function getSpecialPriceDiscount(): float
    {
        $discount = 0.0;
        if (($this->price != 0.0) && ($this->specialPrice)) {
            $discount = (($this->price - $this->specialPrice) / $this->price) * 100;
        }
        return $discount;
    }

    public function getPriceCalculated(): float
    {
        $price = $this->getBestPrice();

        if ($this->parent instanceof BeVariantInterface) {
            $parentPrice = $this->parent->getBestPrice();
        } elseif ($this->parent instanceof ProductInterface) {
            $parentPrice = $this->parent->getBestPrice($this->getQuantity());
        } else {
            $parentPrice = 0.0;
        }

        if ($this->priceCalcMethod === 0) {
            return $parentPrice;
        }
        if ($this->priceCalcMethod === 1) {
            return $price;
        }
        if ($this->priceCalcMethod === 2) {
            return $parentPrice - $price;
        }
        if ($this->priceCalcMethod === 3) {
            return $parentPrice - (($price / 100) * $parentPrice);
        }
        if ($this->priceCalcMethod === 4) {
            return $parentPrice + $price;
        }
        if ($this->priceCalcMethod === 5) {
            return $parentPrice + (($price / 100) * $parentPrice);
        }

        throw new \InvalidArgumentException('Unkonwn price calc method', 1711969492);
    }

    public function getBestPriceCalculated(): float
    {
        return $this->getPriceCalculated();
    }

    public function getParentPrice(): float
    {
        if ($this->priceCalcMethod === 1) {
            return 0.0;
        }

        if ($this->parent instanceof BeVariantInterface) {
            return $this->parent->getBestPrice();
        }

        return $this->parent->getBestPrice($this->getQuantity());
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;

        $this->reCalc();
    }

    public function getPriceCalcMethod(): int
    {
        return $this->priceCalcMethod;
    }

    public function setPriceCalcMethod(int $priceCalcMethod): void
    {
        $this->priceCalcMethod = $priceCalcMethod;
    }

    public function getSkuDelimiter(): string
    {
        return $this->skuDelimiter;
    }

    public function setSkuDelimiter(string $skuDelimiter): void
    {
        $this->skuDelimiter = $skuDelimiter;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getCompleteSku(): string
    {
        $sku = '';

        if ($this->parent instanceof BeVariantInterface) {
            $sku = $this->parent->getCompleteSku();
        } elseif ($this->parent instanceof ProductInterface) {
            $sku = $this->parent->getSku();
        }

        $sku .= $this->skuDelimiter . $this->sku;

        return $sku;
    }

    public function getCompleteSkuWithoutProduct(): string
    {
        $sku = '';

        if ($this->parent instanceof BeVariantInterface) {
            $sku = $this->parent->getCompleteSkuWithoutProduct();
        }

        $sku .= $this->skuDelimiter . $this->sku;

        return $sku;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getGross(): float
    {
        $this->calcGross();
        return $this->gross;
    }

    public function getNet(): float
    {
        $this->calcNet();
        return $this->net;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function getTaxClass(): TaxClass
    {
        return $this->parent->getTaxClass();
    }

    public function setQuantity(int $newQuantity): void
    {
        $this->quantity = $newQuantity;

        $this->reCalc();
    }

    public function changeQuantity(int $newQuantity): void
    {
        $this->quantity = $newQuantity;

        if ($this->beVariants) {
            foreach ($this->beVariants as $beVariant) {
                $beVariant->changeQuantity($newQuantity);
            }
        }

        $this->reCalc();
    }

    public function changeVariantsQuantity(array $variantQuantityArray): void
    {
        foreach ($variantQuantityArray as $beVariantId => $quantity) {
            $beVariant = $this->beVariants[$beVariantId];

            if (is_array($quantity)) {
                $beVariant->changeVariantsQuantity($quantity);
            } else {
                $beVariant->changeQuantity($quantity);
            }
            $this->reCalc();
        }
    }

    public function addBeVariants(array $newVariants): void
    {
        foreach ($newVariants as $newVariant) {
            $this->addBeVariant($newVariant);
        }
    }

    public function addBeVariant(BeVariantInterface $newBeVariant): void
    {
        $newBeVariantId = $newBeVariant->getId();

        $beVariant = $this->beVariants[$newBeVariantId];

        if ($beVariant) {
            if ($beVariant->getBeVariants()) {
                $beVariant->addBeVariants($newBeVariant->getBeVariants());
            } else {
                $newQuantity = $beVariant->getQuantity() + $newBeVariant->getQuantity();
                $beVariant->setQuantity($newQuantity);
            }
        } else {
            $this->beVariants[$newBeVariantId] = $newBeVariant;
        }

        $this->reCalc();
    }

    public function getBeVariants(): array
    {
        return $this->beVariants;
    }

    public function getBeVariantById(int $beVariantId): ?BeVariantInterface
    {
        return $this->beVariants[$beVariantId] ?? null;
    }

    /**
     * @return bool|int
     */
    public function removeBeVariants(array $beVariantsArray)
    {
        foreach ($beVariantsArray as $beVariantId => $value) {
            $beVariant = $this->beVariants[$beVariantId];
            if ($beVariant) {
                if (is_array($value)) {
                    $beVariant->removeBeVariants($value);

                    if (!$beVariant->getBeVariants()) {
                        unset($this->beVariants[$beVariantId]);
                    }

                } else {
                    unset($this->beVariants[$beVariantId]);
                }
                $this->reCalc();
            } else {
                return -1;
            }
        }

        return true;
    }

    protected function calcGross(): void
    {
        if ($this->isNetPrice() === false) {
            if ($this->beVariants) {
                $sum = 0.0;
                foreach ($this->beVariants as $beVariant) {
                    $sum += $beVariant->getGross();
                }
                $this->gross = $sum;
            } else {
                $this->gross = $this->getBestPriceCalculated() * $this->quantity;
            }
        } else {
            $this->calcNet();
            $this->calcTax();
            $this->gross = $this->net + $this->tax;
        }
    }

    protected function calcTax(): void
    {
        if ($this->isNetPrice() === false) {
            $this->calcGross();
            $this->tax = ($this->gross / (1 + $this->getTaxClass()->getCalc())) * ($this->getTaxClass()->getCalc());
        } else {
            $this->calcNet();
            $this->tax = ($this->net * $this->getTaxClass()->getCalc());
        }
    }

    protected function calcNet(): void
    {
        if ($this->isNetPrice() === true) {
            if ($this->beVariants) {
                $sum = 0.0;
                foreach ($this->beVariants as $beVariant) {
                    $sum += $beVariant->getNet();
                }
                $this->net = $sum;
            } else {
                $this->net = $this->getBestPriceCalculated() * $this->quantity;
            }
        } else {
            $this->calcGross();
            $this->calcTax();
            $this->net = $this->gross - $this->tax;
        }
    }

    protected function reCalc(): void
    {
        if ($this->beVariants) {
            $quantity = 0;
            foreach ($this->beVariants as $beVariant) {
                $quantity += $beVariant->getQuantity();
            }

            if ($this->quantity != $quantity) {
                $this->quantity = $quantity;
            }
        }

        if ($this->isNetPrice() === false) {
            $this->calcGross();
            $this->calcTax();
            $this->calcNet();
        } else {
            $this->calcNet();
            $this->calcTax();
            $this->calcGross();
        }
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function setMin(int $min): void
    {
        if ($min < 0 || $min > $this->max) {
            throw new \InvalidArgumentException();
        }

        $this->min = $min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function setMax(int $max): void
    {
        if ($max < 0 || $max < $this->min) {
            throw new \InvalidArgumentException();
        }

        $this->max = $max;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }
}
