<?php

namespace App\Services\Connectors;

interface ConnectorAdapterInterface
{
    /**
     * @return array<int,array{title:string,description:string,url:string,published_date:string,organization:string,raw_payload:string}>
     */
    public function collect(array $source): array;
}
