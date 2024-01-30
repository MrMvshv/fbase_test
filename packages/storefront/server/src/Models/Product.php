<?php

namespace Fleetbase\Storefront\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Models\Category;
use Fleetbase\Models\File;
use Fleetbase\Models\User;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicid;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Milon\Barcode\Facades\DNS2DFacade as DNS2D;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ProductStatus
{
    public const AVAILABLE = 'available';
    public const DRAFT     = 'draft';
}

class Product extends StorefrontModel
{
    use HasUuid;
    use HasPublicid;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use HasSlug;
    use Searchable;

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'product';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * These attributes that can be queried.
     *
     * @var array
     */
    protected $searchableColumns = ['name', 'description'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_uuid',
        'primary_image_uuid',
        'created_by_uuid',
        'store_uuid',
        'category_uuid',
        'name',
        'description',
        'tags',
        'translations',
        'meta',
        'qr_code',
        'barcode',
        'youtube_urls',
        'sku',
        'price',
        'currency',
        'sale_price',
        'is_service',
        'is_bookable',
        'is_available',
        'is_on_sale',
        'is_recommended',
        'can_pickup',
        'status',
        'slug',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_service'     => 'boolean',
        'is_bookable'    => 'boolean',
        'is_on_sale'     => 'boolean',
        'is_available'   => 'boolean',
        'is_recommended' => 'boolean',
        'can_pickup'     => 'boolean',
        'tags'           => 'array',
        'meta'           => Json::class,
        'translations'   => Json::class,
        'youtube_urls'   => Json::class,
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = ['primary_image_url', 'store_id', 'meta_array'];

    /**
     * Attributes that is filterable on this model.
     *
     * @var array
     */
    protected $filterParams = ['category_slug', 'category'];

    /**
     * Generates QR Code & Barcode on creation.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->qr_code = DNS2D::getBarcodePNG($model->uuid, 'QRCODE');
            $model->barcode = DNS2D::getBarcodePNG($model->uuid, 'PDF417');
        });
    }

    /**
     * @var \Spatie\Sluggable\SlugOptions
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(Category::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function addonCategories()
    {
        return $this->hasMany(ProductAddonCategory::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function primaryImage()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(File::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function files()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->hasMany(File::class, 'subject_uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'subject_uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes()
    {
        return $this->hasMany(Vote::class, 'subject_uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hours()
    {
        return $this->hasMany(ProductHour::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return array
     */
    public function getMetaArrayAttribute()
    {
        $_meta = [];

        if (empty($this->meta)) {
            return $_meta;
        }

        foreach ($this->meta as $key => $value) {
            $_meta[] = [
                'key'   => Str::snake($key),
                'label' => Utils::smartHumanize($key),
                'value' => $value,
            ];
        }

        return $_meta;
    }

    /**
     * @return string
     */
    public function getPrimaryImageUrlAttribute()
    {
        $default   = $this->primaryImage->url ?? null;
        $secondary = $this->files->first()->url ?? null;
        $backup    = 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/image-file-icon.png';

        return $default ?? $secondary ?? $backup;
    }

    /**
     * @return string
     */
    public function getStoreIdAttribute()
    {
        return static::attributeFromCache($this, 'store.public_id', function () {
            return $this->store()->select(['public_id'])->first()->public_id;
        });
    }

    /**
     * Set the price as only numbers.
     *
     * @void
     */
    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = Utils::numbersOnly($value);
    }

    /**
     * Set the sale price as only numbers.
     *
     * @void
     */
    public function setSalePriceAttribute($value)
    {
        $this->attributes['sale_price'] = Utils::numbersOnly($value);
    }

    /**
     * Sets or updates addon categories for the product.
     *
     * This function iterates over each addon category in the given array. If the category exists (identified by UUID),
     * it updates the existing category. Otherwise, it creates a new category and adds it to the product.
     *
     * @param array $addonCategories An array of addon categories data. Each category should have keys like 'uuid',
     *                               'excluded_addons', 'max_selectable', and 'is_required'.
     *
     * @return Product returns the instance of the Product for method chaining
     */
    public function setAddonCategories(array $addonCategories = []): Product
    {
        foreach ($addonCategories as $addonCategory) {
            // get uuid if set
            $id = data_get($addonCategory, 'uuid');

            // update product addon category
            if (Str::isUuid($id)) {
                ProductAddonCategory::where('uuid', $id)->update([
                    'excluded_addons' => data_get($addonCategory, 'excluded_addons'),
                    'max_selectable'  => data_get($addonCategory, 'max_selectable'),
                    'is_required'     => data_get($addonCategory, 'is_required'),
                ]);
                continue;
            }

            // create new product addon category
            $productAddonCategory = ProductAddonCategory::create([
                'product_uuid'    => $this->uuid,
                'category_uuid'   => data_get($addonCategory, 'category_uuid'),
                'excluded_addons' => data_get($addonCategory, 'excluded_addons'),
                'max_selectable'  => data_get($addonCategory, 'max_selectable'),
                'is_required'     => data_get($addonCategory, 'is_required'),
            ]);

            // insert to hasmany relation
            $this->addonCategories->push($productAddonCategory);
        }

        return $this;
    }

    /**
     * Sets or updates product variants for the product.
     *
     * Iterates through each variant in the provided array. If the variant exists (identified by UUID), it updates
     * the variant with new values. If it doesn't exist, a new variant is created. Also handles the setting or
     * updating of product variant options.
     *
     * @param array $variants An array of product variants data. Each variant should have keys like 'uuid', 'name',
     *                        'description', 'translations', 'meta', 'is_multiselect', 'is_required', 'min', 'max',
     *                        and 'options'.
     *
     * @return Product returns the instance of the Product for method chaining
     */
    public function setProductVariants(array $variants = []): Product
    {
        foreach ($variants as $variant) {
            $id = data_get($variant, 'uuid');

            if (Str::isUuid($id)) {
                // Update the existing product variant with new values if any changed
                ProductVariant::where('uuid', $id)->update([
                    'name'           => data_get($variant, 'name'),
                    'description'    => data_get($variant, 'name'),
                    'translations'   => data_get($variant, 'translations', []),
                    'meta'           => data_get($variant, 'meta', []),
                    'is_multiselect' => data_get($variant, 'is_multiselect'),
                    'is_required'    => data_get($variant, 'is_required'),
                    'min'            => data_get($variant, 'min'),
                    'max'            => data_get($variant, 'max'),
                ]);

                // Update product variant options if applicable
                if (is_array($variant['options'])) {
                    foreach ($variant['options'] as $option) {
                        $optionId = data_get($option, 'uuid');

                        if (Str::isUuid($optionId)) {
                            // make sure additional cost is always numbers only
                            if (isset($option['additional_cost'])) {
                                $option['additional_cost'] = Utils::numbersOnly($option['additional_cost']);
                            }

                            $productVariantOptionInput = Arr::except($option, ['uuid']);
                            ProductVariantOption::where('uuid', $option['uuid'])->update($productVariantOptionInput);
                            continue;
                        }

                        $option['product_variant_uuid'] = $id;
                        ProductVariantOption::create($option);
                    }
                }

                continue;
            }

            $variant['created_by_uuid'] = session('user');
            $variant['company_uuid']    = session('company');
            $variant['product_uuid']    = $this->uuid;

            $productVariantInput = Arr::except($variant, ['options']);
            $productVariant      = ProductVariant::create($productVariantInput);

            if (is_array($variant['options'])) {
                foreach ($variant['options'] as $option) {
                    $option['product_variant_uuid'] = $productVariant->uuid;
                    ProductVariantOption::create($option);
                }
            }
        }

        return $this;
    }

    /**
     * Finds products that match the specified search query, store, and network.
     *
     * @param string      $search  the search query to use to find products
     * @param string|null $store   The store to search for products in. If null, all stores are searched.
     * @param int         $limit   the maximum number of products to return
     * @param string|null $network The network to search for products on. If null, the session network is used.
     *
     * @return \Illuminate\Database\Eloquent\Collection the collection of products that match the search query, store, and network
     */
    public static function findFromNetwork($search, $store = null, $limit = 20, $network = null): ?\Illuminate\Database\Eloquent\Collection
    {
        $network = session('storefront_network', $network);

        $results = static::whereHas('store', function ($query) use ($network, $store) {
            if ($store) {
                $query->where('public_id', $store);
            }

            $query->whereHas('networks', function ($networksQuery) use ($network) {
                $networksQuery->where('network_uuid', $network);
            });
        })
            ->search($search)
            ->whereIsAvailable(1)
            ->whereStatus('published')
            ->limit($limit)
            ->get();

        return $results;
    }
}
