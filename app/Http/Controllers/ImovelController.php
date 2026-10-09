<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImovelRequest;
use App\Http\Requests\PublicarImovelRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use App\Services\ImovelService;
use App\Support\Sessao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ImovelController extends Controller
{
    public function __construct(private readonly ImovelService $imoveis) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $utilizador = $this->gestor($request);
        $consulta = Imovel::query()->with('imagens');

        if ($utilizador === null) {
            $consulta->where('publicado', true);
        } elseif ($request->has('publicado')) {
            $consulta->where('publicado', $request->boolean('publicado'));
        }

        $this->filtrar($consulta, $request);

        if ($utilizador === null) {
            $consulta->orderBy('ordem')->orderByDesc('created_at');
        } else {
            $this->ordenar($consulta, $request, 'created_at', 'desc');
        }

        if ($request->boolean('paginar')) {
            return ImovelResource::collection(
                $consulta->paginate(min($request->integer('per_page', 15), 100))->withQueryString()
            );
        }

        return ImovelResource::collection($consulta->get());
    }

    public function destaque(Request $request): AnonymousResourceCollection
    {
        $consulta = Imovel::query()->with('imagens')->where('publicado', true)->where('destaque', true);
        $this->filtrar($consulta, $request);

        return ImovelResource::collection($consulta->orderBy('ordem')->orderByDesc('created_at')->get());
    }

    public function show(Request $request, Imovel $imovel): ImovelResource
    {
        $utilizador = $this->gestor($request);
        if ($utilizador === null && ! $imovel->publicado) {
            abort(404, 'O registo já não existe.');
        }

        return new ImovelResource($imovel->load('imagens'));
    }

    public function store(ImovelRequest $request): JsonResponse
    {
        $imovel = $this->imoveis->criar($request->validated());

        return (new ImovelResource($imovel))->response()->setStatusCode(201);
    }

    public function update(ImovelRequest $request, Imovel $imovel): ImovelResource
    {
        return new ImovelResource($this->imoveis->atualizar($imovel, $request->validated()));
    }

    public function publicar(PublicarImovelRequest $request, Imovel $imovel): ImovelResource
    {
        return new ImovelResource($this->imoveis->publicar($imovel, $request->boolean('publicado')));
    }

    public function destroy(Imovel $imovel): Response
    {
        $imovel->delete();

        return response()->noContent();
    }

    private function gestor(Request $request): ?\App\Models\User
    {
        $utilizador = Sessao::opcional($request);
        if ($utilizador !== null && ! $utilizador->pode('imoveis')) {
            abort(403, 'Não tem permissão para esta ação.');
        }

        return $utilizador;
    }

    private function filtrar($consulta, Request $request): void
    {
        if ($q = trim($request->string('q')->toString())) {
            $consulta->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhere('zona', 'like', "%{$q}%")
                    ->orWhere('id', 'like', "%{$q}%");
            });
        }

        foreach (['estado', 'tipo', 'finalidade', 'zona'] as $campo) {
            if ($request->filled($campo)) {
                $consulta->where($campo, $request->string($campo)->toString());
            }
        }

        if ($request->filled('preco_min')) {
            $consulta->where('preco', '>=', $request->integer('preco_min'));
        }
        if ($request->filled('preco_max')) {
            $consulta->where('preco', '<=', $request->integer('preco_max'));
        }
    }

    private function ordenar($consulta, Request $request, string $defeito, string $direcao): void
    {
        $mapa = [
            'preco' => 'preco',
            'titulo' => 'titulo',
            'criado' => 'created_at',
            'ordem' => 'ordem',
        ];
        $pedido = $request->string('ordenar')->toString();
        $desc = str_starts_with($pedido, '-');
        $chave = ltrim($pedido, '-');

        if (! isset($mapa[$chave])) {
            $consulta->orderBy($defeito, $direcao);

            return;
        }

        $consulta->orderBy($mapa[$chave], $desc ? 'desc' : 'asc');
    }
}
