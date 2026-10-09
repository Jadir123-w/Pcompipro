@extends('tenant.layouts.app')

@section('content')

    <div>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0 font-size-18">Usuario no verificado</h4>
    
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="#">Usuario no verificado</a></li>
                            <li class="breadcrumb-item active">Usuario no verificado</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                
                <div class="row p-4">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Revise su bandeja de entrada en el correo: <b>{{ auth()->user()->email }}</b> y haga clic en el enlace "Verificar Email" para activar su cuenta.</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection
