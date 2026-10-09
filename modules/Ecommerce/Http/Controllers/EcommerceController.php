<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Http\Resources\Tenant\ItemCollection;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\User;
use App\Models\Tenant\Person;
use Illuminate\Support\Str;
use App\Models\Tenant\Order;
use App\Models\Tenant\ItemsRating;
use App\Models\Tenant\ConfigurationEcommerce;
use Modules\Ecommerce\Http\Resources\ItemBarCollection;
use stdClass;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\CulqiEmail;
use App\Http\Controllers\Tenant\Api\ServiceController;
use Illuminate\Support\Facades\Validator;
use Modules\Inventory\Models\InventoryConfiguration;
use App\Http\Resources\Tenant\OrderCollection;
use App\Models\Tenant\Promotion;
use Modules\ApiPeruDev\Data\ServiceData;
use App\Models\Tenant\Document;
use Modules\Item\Models\Category;


class EcommerceController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function __construct()
    {
        // Evita consultas tenant cuando Artisan está corriendo (route:list, config:cache, etc.)
        if (app()->runningInConsole()) {
            return;
        }

        // Carga "records" solo en requests HTTP reales (con tenant ya resuelto)
        $this->middleware(function ($request, $next) {
            view()->share(
                'records',
                Item::where('apply_store', 1)
                    ->orderBy('id', 'DESC')
                    ->take(2)
                    ->get()
            );

            return $next($request);
        });
    }


    // public function index()
    // {
    //   $dataPaginate = Item::where([['apply_store', 1], ['internal_id','!=', null]])->paginate(15);
    //   $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }
    public function index($name = null)
    {
        if ($name) {
            $name = str_replace('-', ' ', $name);
        }

        $category = Category::where('name', $name)->first();
        
        $dataPaginate = Item::where([['apply_store', 1], ['internal_id', '!=', null]])
            ->orderBy('created_at', 'DESC')
            ->category($category ? $category->id : null)
            ->paginate(8);
        $configuration = InventoryConfiguration::first();
        $categories = Category::get();

        return view('ecommerce::index', [
            'dataPaginate' => $dataPaginate,
            'configuration' => $configuration->stock_control,
        ])->with('categories', $categories);
    }
    
    // public function category(Request $request)
    // {
    //   $dataPaginate = Item::select('i.*')
    //     ->where([['i.apply_store', 1], ['i.internal_id','!=', null], ['it.tag_id', $request->category]])
    //     ->from('items as i')
    //     ->join('item_tags as it', 'it.item_id','i.id')->paginate(15);
    //     $configuration = InventoryConfiguration::first();
    //   return view('ecommerce::index', ['dataPaginate' => $dataPaginate, 'configuration' => $configuration->stock_control]);
    // }

    public function getDescriptionWithPromotion($item, $promotion_id)
    {
        $promotion = Promotion::findOrFail($promotion_id);

        return "{$item->description} - {$promotion->name}";
    }

    public function item($id, $promotion_id = null)
    {
        $row = Item::find($id);
        $exchange_rate_sale = $this->getExchangeRateSale();
        $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price*1.18;

        $description = $promotion_id ? $this->getDescriptionWithPromotion($row, $promotion_id) : $row->description;

        $record = (object)[
            'id' => $row->id,
            'internal_id' => $row->internal_id,
            'unit_type_id' => $row->unit_type_id,
            'description' => $description,
            'category' => $row->category,
            'stock' => $row->stock,
            // 'description' => $row->description,
            'technical_specifications' => $row->technical_specifications,
            'name' => $row->name,
            'second_name' => $row->second_name,
            'sale_unit_price' => ($row->currency_type_id === 'PEN') ? $sale_unit_price : ($sale_unit_price * $exchange_rate_sale),
            'currency_type' => $row->currency_type,
            'has_igv' => (bool) $row->has_igv,
            'sale_unit' => $row->sale_unit_price,
            'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
            'currency_type_symbol' => $row->currency_type->symbol,
            'image' =>  $row->image,
            'image_medium' => $row->image_medium,
            'image_small' => $row->image_small,
            'tags' => $row->tags->pluck('tag_id')->toArray(),
            'images' => $row->images,
            'attributes' => $row->attributes ? $row->attributes : [],
            'promotion_id' => $promotion_id,
        ];

        return view('ecommerce::items.record', compact('record'));
    }

    public function items()
    {
        $records = Item::where('apply_store', 1)->get();
        return view('ecommerce::items.index', compact('records'));
    }

    public function itemsBar()
    {
        $records = Item::where('apply_store', 1)->get();
        // return new ItemCollection($records);
        return new ItemBarCollection($records);

    }

    public function partialItem($id)
    {
        $record = Item::find($id);
        return view('ecommerce::items.partial', compact('record'));
    }

    public function detailCart()
    {
        $configuration = ConfigurationEcommerce::first();
        return view('ecommerce::cart.detail', compact(['configuration']));
    }

    public function orderList()
    {
        if (auth('ecommerce')->user()) {
            $configuration = ConfigurationEcommerce::first();
            return view('ecommerce::document_list.order', compact('configuration'));
        } else {
            return redirect('ecommerce');
        }

    }

    public function documentList()
    {
        // dd(auth('ecommerce')->user());
        if (auth('ecommerce')->user()) {
            return view('ecommerce::document_list.document');
        } else {
            return redirect('ecommerce');
        }
    }

    public function orders(Request $request)
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();
            
            // Inicializar la consulta base
            $records = Order::where(function($query) use ($user) {
                $query->where('customer', 'LIKE', '%' . $user->email . '%')
                      ->orWhereJsonContains('customer->correo_electronico', $user->email);
            });
            
            // Aplicar filtro de estado si se proporciona
            if ($request->state_order_id) {
                $state_allowed = $request->state_order_id && $request->state_order_id != 'all' ? [$request->state_order_id] : [1, 2, 3, 4];
                $records = $records->whereIn('status_order_id', $state_allowed);
            }

            // Aplicar filtro de fecha si se proporciona
            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $records = $records->whereBetween('created_at', [$date_of_start, $date_of_end]);
            }
            
            // Obtener los resultados paginados
            $records = $records->paginate(config('tenant.items_per_page', 10));
            
            // Transformar los datos manteniendo la estructura de paginación
            $records->getCollection()->transform(function ($row) {
                return $row->getCollectionData();
            });

            return $records;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }
    }

    public function documents(Request $request) 
    {
        if (auth('ecommerce')->user()) {
            $user = auth('ecommerce')->user();

            
            // Buscar órdenes del usuario
            $orders = Order::where(function($query) use ($user) {
                        $query->where('customer', 'LIKE', '%' . $user->email . '%')
                              ->orWhereJsonContains('customer->correo_electronico', $user->email);
                    })->get();
            
            $arrays_external_id = $orders->pluck('document_external_id')->filter()->toArray();
            
            if (empty($arrays_external_id)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => config('tenant.items_per_page', 10),
                    'total' => 0
                ]);
            }

            $documents = Document::where(function($q) use($arrays_external_id, $user, $request) {
                            $q->whereIn('external_id', $arrays_external_id)
                                ->orWhere('customer->email', $user->email);
                        });

            if ($request->date_of_endd || $request->date_of_start) {
                $date_of_start = $request->date_of_start ?? date('Y-m-d');
                $date_of_end = $request->date_of_endd ?? date('Y-m-d');
                $documents = $documents->whereBetween('date_of_issue', [$date_of_start, $date_of_end]);
            }

            if ($request->state_type_id ) {
                $state_allowed = $request->state_type_id && $request->state_type_id != 'all'  ? [$request->state_type_id] : ['05', '09', '11'];
                $documents = $documents->whereIn('state_type_id', $state_allowed);
            }

            $documents = $documents->orderBy('date_of_issue', 'desc')
                        ->paginate(config('tenant.items_per_page'));
            
            // Transformar los datos manteniendo la estructura de paginación
            $documents->getCollection()->transform(function ($dc) {
                return [
                    'number' => $dc->getNumberFullAttribute(),
                    'description' => $dc->document_type->description,
                    'date_of_issue' => $dc->date_of_issue->format('Y-m-d'),
                    'customer' => [
                        'name' => $dc->customer->name,
                        'number' => $dc->customer->number,
                        'identity_document_type_id' => $dc->customer->identity_document_type_id,
                    ],
                    'status' => $dc->state_type->description,
                    'state_type_id' => $dc->state_type_id,
                    'download_pdf' => $dc->download_external_pdf,
                    'download_xml' =>  $dc->download_external_xml,
                    'total' => $dc->total,
                ];
            });

            return $documents;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No autentificado'
            ], 401);
        }
    }

    public function pay()
    {
        return view('ecommerce::cart.pay');
    }

    public function showLogin()
    {
        return view('ecommerce::user.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('ecommerce')->attempt($credentials)) {
           return[
               'success' => true,
               'message' => 'Login Success'
           ];
        }
        else{
            return[
                'success' => false,
                'message' => 'Usuario o Password incorrectos'
            ];
        }

    }

    public function logout()
    {
        Auth::guard('ecommerce')->logout();
        
        $referer = request()->headers->get('referer');
        
        if ($referer && (str_contains($referer, '/pedidos') || str_contains($referer, 'pedidos/'))) {
            return redirect('/pedidos');
        }
        
        return redirect('ecommerce');
    }

    public function storeUser(Request $request)
    {
        try{

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'ruc' => 'required|string|min:8|max:11',
                'name' => 'nullable|string|max:255',
                'pswd' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => $validator->errors()->first()
                ];
            }

            $verify = Person::where('email', $request->email)
                        ->orWhere('number', $request->ruc)
                        ->first();
            if($verify)
            {
                return [
                    'success' => false,
                    'message' => 'Email o RUC/DNI no disponible'
                ];
            }

            $type = (strlen($request->ruc)==8) ? 'dni' : 'ruc';
            $name = $request->name;
            $identity_document_type_id = (strlen($request->ruc)==8) ? 1 : 6;
            $address = null;
            $department_id = null;
            $province_id = null;
            $district_id = null;

            $dataDocument = $this->searchDocument($type,$request->ruc);


            if($dataDocument["success"]){
                $name = $dataDocument["data"]["name"];
                if($type==='ruc'){
                    $address = $dataDocument['data']['address'];
                    $departmentId = $dataDocument['data']['location_id'][0] ?? null;
                    $provinceId = $dataDocument['data']['location_id'][1] ?? null;
                    $districtId = $dataDocument['data']['location_id'][2] ?? null;
                }
            }
            
            if(!($dataDocument["success"]) && $type==='dni'){
                $identity_document_type_id = 0;
            }
            
            $person = new Person();
            $person->type = 'customers';
            $person->identity_document_type_id = $identity_document_type_id;
            $person->number = $request->ruc;
            $person->name = $name;
            $person->country_id = 'PE';
            $person->nationality_id = 'PE';
            $person->department_id = $department_id;
            $person->province_id = $province_id;
            $person->district_id = $district_id;
            $person->address = $address;
            $person->establishment_code = '0000';
            $person->email = $request->email;
            $person->password = bcrypt($request->pswd);
            
            $person->save();

            $credentials = [ 'email' => $person->email, 'password' => $request->pswd ];
            Auth::guard('ecommerce')->attempt($credentials);
            return [
                'success' => true,
                'message' => 'Usuario registrado'
            ];

        }catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }

    }

    public function transactionFinally(Request $request)
    {
        try{
            //1. confirmar dato de comprobante en order
            $order_generated = Order::find($request->orderId);
            $order_generated->document_external_id = $request->document_external_id;
            $order_generated->number_document = $request->number_document;
            $order_generated->save();

            return [
                'success' => true,
                'message' => 'Order Actualizada',
                'order_total' => $order_generated->total
            ];
        }
        catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }

    }

    public function paymentCash(Request $request)
    {
        
        $validator = Validator::make($request->customer, [
            'telefono' => 'required|numeric',
            'direccion' => 'required',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        } else {
            try {
                $type = ($request->purchase["datos_del_cliente_o_receptor"]["codigo_tipo_documento_identidad"]=='6')?'ruc':'dni';
                $document_number = $request->purchase["datos_del_cliente_o_receptor"]["numero_documento"];
                
                $dataDocument = $this->searchDocument($type,$document_number);
                if ($dataDocument["success"]) {
                    $clientData = [ "apellidos_y_nombres_o_razon_social" => $dataDocument["data"]["name"] ];
                    if ($type === 'ruc') {
                        $clientData["direccion"] = $dataDocument['data']['address'];
                        $clientData["ubigeo"] = $dataDocument['data']['location_id'][2] ?? null;
                    }
                    $request->merge([
                        'purchase' => array_merge($request->purchase, [
                            "datos_del_cliente_o_receptor" => array_merge(
                                $request->purchase["datos_del_cliente_o_receptor"],
                                $clientData
                            )
                        ])
                    ]);
                }

                $user = auth('ecommerce')->user();
                $order = Order::create([
                'external_id' => Str::uuid()->toString(),
                'customer' =>  $request->customer,
                'shipping_address' => 'direccion 1',
                'items' =>  $request->items,
                'total' => $request->precio_culqi,
                'reference_payment' => 'efectivo',
                'status_order_id' => 1,
                'purchase' => $request->purchase
              ]);

            $customer_email = $user->email;
            $document = new stdClass;
            $document->client = $user->name;
            $document->product = $request->producto;
            $document->total = $request->precio_culqi;
            $document->items = $request->items;

            $this->paymentCashEmail($customer_email, $document);

            //Mail::to($customer_email)->send(new CulqiEmail($document));
            return [
                'success' => true,
                'order' => $order
            ];

        }catch(Exception $e)
        {
            return [
                'success' => false,
                'message' =>  $e->getMessage()
            ];
        }
      }
    }

    public function paymentCashEmail($customer_email, $document)
    {
        try {
            $email = $customer_email;
            $mailable = new CulqiEmail($document);
            $id = (int) $document->id;
            $model = __FILE__.";;".__LINE__;
            $sendIt = EmailController::SendMail($email, $mailable, $id, $model);
            /*
            Configuration::setConfigSmtpMail();
            $array_email = explode(',', $customer_email);
            if (count($array_email) > 1) {
                foreach ($array_email as $email_to) {
                    $email_to = trim($email_to);
                if(!empty($email_to)) {
                        Mail::to($email_to)->send(new CulqiEmail($document));
                    }
                }
            } else {
                Mail::to($customer_email)->send(new CulqiEmail($document));
            }*/
        }catch(\Exception $e)
        {
            return true;
        }
    }

    public function ratingItem(Request $request)
    {
        if(auth('ecommerce')->user())
        {
            $user_id = auth('ecommerce')->id();
            $row = ItemsRating::firstOrNew( ['user_id' => $user_id, 'item_id' => $request->item_id ] );
            $row->value = $request->value;
            $row->save();
            return[
                'success' => false,
                'message' => 'Rating Guardado'
            ];
        }
        return[
            'success' => false,
            'message' => 'No se guardo Rating'
        ];

    }

    public function getRating($id)
    {
        if(auth('ecommerce')->user())
        {
            $user_id = auth('ecommerce')->id();
            $row = ItemsRating::where('user_id', $user_id)->where('item_id', $id)->first();
            return[
                'success' => true,
                'value' => ($row) ? $row->value : 0,
                'message' => 'Valor Obtenido'
            ];
        }
        return[
            'success' => false,
            'value' => 0,
            'message' => 'No se obtuvo valor'
        ];

    }

    private function getExchangeRateSale(){

        $exchange_rate = app(ServiceController::class)->exchangeRateTest(date('Y-m-d'));

        return (array_key_exists('sale', $exchange_rate)) ? $exchange_rate['sale'] : 1;


    }

    public function saveDataUser(Request $request)
    {
        $user = auth('ecommerce')->user();
        if ($request->address) {
            $user->address = $request->address;
        }
        if ($user->telephone = $request->telephone) {
            $user->telephone = $request->telephone;
        }

        $user->save();

        return ['success' => true];

    }

    public function searchDocument($type, $number)
    {
        return (new ServiceData)->service($type, $number);
    }


}
