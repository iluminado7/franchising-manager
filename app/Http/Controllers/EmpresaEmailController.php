<?php

namespace App\Http\Controllers;

use App\Models\EmpresaEmail;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmpresaEmailController extends Controller
{
    // GET /api/empresas/{empresaId}/emails
    public function index(int $empresaId): JsonResponse
    {
        $empresa = Empresa::findOrFail($empresaId);
        return response()->json($empresa->emails()->orderBy('tipo')->get());
    }

    // POST /api/empresas/{empresaId}/emails
    public function store(Request $request, int $empresaId): JsonResponse
    {
        Empresa::findOrFail($empresaId); // verificar que existe

        $data = $request->validate([
            'email'     => "required|email|max:200|unique:empresa_emails,email,NULL,id,empresa_id,{$empresaId}",
            'tipo'      => 'required|in:contacto,facturacion',
            'principal' => 'sometimes|boolean',
        ]);

        // Si se marca como principal, desmarcar los otros del mismo tipo
        if (!empty($data['principal'])) {
            EmpresaEmail::where('empresa_id', $empresaId)
                        ->where('tipo', $data['tipo'])
                        ->update(['principal' => 0]);
        }

        $email = EmpresaEmail::create([
            'empresa_id' => $empresaId,
            'email'      => $data['email'],
            'tipo'       => $data['tipo'],
            'principal'  => $data['principal'] ?? false,
        ]);

        return response()->json($email, 201);
    }

    // PUT /api/empresas/{empresaId}/emails/{id}
    public function update(Request $request, int $empresaId, int $id): JsonResponse
    {
        $email = EmpresaEmail::where('id', $id)
                             ->where('empresa_id', $empresaId)
                             ->firstOrFail();

        $data = $request->validate([
            'email'     => "sometimes|email|max:200|unique:empresa_emails,email,{$id},id,empresa_id,{$empresaId}",
            'tipo'      => 'sometimes|in:contacto,facturacion',
            'principal' => 'sometimes|boolean',
        ]);

        if (!empty($data['principal'])) {
            EmpresaEmail::where('empresa_id', $empresaId)
                        ->where('tipo', $data['tipo'] ?? $email->tipo)
                        ->where('id', '!=', $id)
                        ->update(['principal' => 0]);
        }

        $email->update($data);
        return response()->json($email->fresh());
    }

    // DELETE /api/empresas/{empresaId}/emails/{id}
    public function destroy(int $empresaId, int $id): JsonResponse
    {
        $email = EmpresaEmail::where('id', $id)
                             ->where('empresa_id', $empresaId)
                             ->firstOrFail();

        $email->delete();
        return response()->json(['message' => 'Email eliminado.']);
    }
}
