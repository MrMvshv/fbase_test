<?php

namespace Fleetbase\Pallet\Http\Resources;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Inventory extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->when(Http::isInternalRequest(), $this->incrementing_id, $this->public_id),
            'uuid'                  => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'             => $this->when(Http::isInternalRequest(), $this->public_id),
            'product_uuid'          => $this->product_uuid,
            'batch_uuid'            => $this->batch_uuid,
            'warehouse_uuid'        => $this->warehouse_uuid,
            'supplier_uuid'         => $this->supplier_uuid,
            'supplier'              => $this->whenLoaded('supplier', $this->supplier),
            'product'               => $this->whenLoaded('product', $this->product),
            'batch'                 => $this->whenLoaded('batch', new Batch($this->batch)),
            'warehouse'             => $this->whenLoaded('warehouse', new Warehouse($this->warehouse)),
            'quantity'              => (int) $this->quantity,
            'min_quantity'          => (int) $this->min_quantity,
            'comments'              => $this->comments,
            'expiry_date_at'        => $this->expiry_date_at,
            'updated_at'            => $this->updated_at,
            'created_at'            => $this->created_at,
        ];
    }
}
