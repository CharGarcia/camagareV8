<?php

declare(strict_types=1);

namespace App\models;

class AsientoContableDetalle extends BaseModel
{
    protected string $table = 'asientos_contables_detalle';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'id_empresa',
        'id_asiento',
        'id_cuenta_contable',
        'id_centro_costo',
        'id_proyecto',
        'debe',
        'haber',
        'referencia_detalle',
        'documento_referencia',
        'id_entidad',
        'tipo_entidad',
        'eliminado',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by'
    ];
}
