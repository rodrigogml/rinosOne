<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuthorizationServiceIdentity extends Model { protected $table='auth_service_identity'; public const CREATED_AT='createdAt'; public const UPDATED_AT='updatedAt'; protected $fillable=['idOwnerUser','idTenant','displayName','purpose','scope','state','startsAt','endsAt']; protected function casts(): array { return ['startsAt'=>'datetime','endsAt'=>'datetime']; } }
