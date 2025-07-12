<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserRating; // Make sure this is imported

class Exchange extends Model
{
  public const CREATED_AT = 'created';
  protected $table = 'trs_exchange'; // Assuming your table name is trs_exchange
  protected $primaryKey = 'exchange_id';
  public $timestamps = false;
  protected $dates = [
    'completed_at'
  ];

  protected $fillable = [
    'product_id',
    'to_product_id',
    'user_id',
    'to_user_id',
    'status',
    'author',
    'created',
    'completed_at'
  ];

  public function requesterProduct()
  {
    return $this->belongsTo(Product::class, 'product_id');
  }

  public function receiverProduct()
  {
    return $this->belongsTo(Product::class, 'to_product_id');
  }

  public function requester()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function receiver()
  {
    return $this->belongsTo(User::class, 'to_user_id');
  }

  public function exchangeMessage()
  {
    return $this->hasMany(Message::class, 'exchange_id');
  }

  protected static function boot()
  {
    parent::boot();

    static::creating(function ($exchange) {
      $exchange->created = now();
    });
  }

  /**
   * Get the ratings given by the requester of this exchange.
   */
  public function ratingsGivenByRequester()
  {
      return $this->hasMany(UserRating::class, 'exchange_id', 'exchange_id')
                  ->whereColumn('rater_user_id', 'trs_exchange.user_id');
  }

  /**
   * Get the ratings given by the receiver of this exchange.
   */
  public function ratingsGivenByReceiver()
  {
      return $this->hasMany(UserRating::class, 'exchange_id', 'exchange_id')
                  ->whereColumn('rater_user_id', 'trs_exchange.to_user_id');
  }
}
