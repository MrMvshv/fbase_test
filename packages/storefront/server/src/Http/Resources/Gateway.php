<?php

namespace Fleetbase\Storefront\Http\Resources;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Gateway extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'           => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'         => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'    => $this->when(Http::isInternalRequest(), $this->public_id),
            'name'         => $this->name,
            'description'  => $this->description,
            'logo_url'     => $this->logo_url,
            'code'         => $this->code,
            'type'         => $this->type,
            'sandbox'      => $this->sandbox,
            'return_url'   => $this->return_url,
            'callback_url' => $this->callback_url,
        ];
    }
}
