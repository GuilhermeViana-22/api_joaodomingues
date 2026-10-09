<?php

namespace App\Services;

use App\Models\Imovel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImovelService
{
    public function criar(array $dados): Imovel
    {
        return DB::transaction(function () use ($dados) {
            $referencia = $this->referencia($dados['referencia'] ?? '');
            $id = $this->idLivre(Str::slug($referencia));

            $imovel = Imovel::query()->create([
                ...$this->atributos($dados, $referencia),
                'id' => $id,
                'slug' => $id,
            ]);

            $this->sincronizarImagens($imovel, $dados['imagens'] ?? []);

            return $imovel->load('imagens');
        });
    }

    public function atualizar(Imovel $imovel, array $dados): Imovel
    {
        return DB::transaction(function () use ($imovel, $dados) {
            $referencia = $this->referencia($dados['referencia'] ?? $imovel->referencia, $imovel);
            $imovel->fill($this->atributos($dados, $referencia));
            $imovel->save();

            if (array_key_exists('imagens', $dados)) {
                $this->sincronizarImagens($imovel, $dados['imagens'] ?? []);
            }

            return $imovel->load('imagens');
        });
    }

    public function publicar(Imovel $imovel, bool $publicado): Imovel
    {
        if ($publicado && $imovel->imagens()->count() === 0) {
            throw ValidationException::withMessages([
                'imagens' => 'Adicione pelo menos a foto de destaque antes de publicar.',
            ]);
        }

        $imovel->publicado = $publicado;
        $imovel->save();

        return $imovel->load('imagens');
    }

    /** @param  list<string>  $urls */
    public function sincronizarImagens(Imovel $imovel, array $urls): void
    {
        $anteriores = $imovel->imagens()->pluck('caminho');
        $imovel->imagens()->delete();

        foreach (array_values($urls) as $ordem => $url) {
            $imovel->imagens()->create([
                'caminho' => $url,
                'ordem' => $ordem,
            ]);
        }

        $mantidas = collect($urls);
        foreach ($anteriores as $caminho) {
            if (! $mantidas->contains($caminho)) {
                $this->apagarFicheiroLocal((string) $caminho);
            }
        }
    }

    public function apagarFicheiroLocal(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $prefixo = '/storage/';
        if (! str_starts_with($path, $prefixo)) {
            return;
        }

        $relativo = substr($path, strlen($prefixo));
        if (! str_starts_with($relativo, 'imoveis/')) {
            return;
        }

        Storage::disk('public')->delete($relativo);
    }

    private function referencia(string $informada, ?Imovel $actual = null): string
    {
        $ref = strtoupper(trim($informada));
        if ($ref !== '') {
            return $ref;
        }

        if ($actual !== null) {
            return $actual->referencia;
        }

        $maximo = Imovel::withTrashed()
            ->pluck('referencia')
            ->map(fn (string $r) => (int) preg_replace('/\D/', '', $r))
            ->max() ?? 0;

        return 'JD-'.str_pad((string) ($maximo + 1), 3, '0', STR_PAD_LEFT);
    }

    private function idLivre(string $base): string
    {
        $base = $base !== '' ? $base : 'imovel';
        $id = $base;
        $n = 2;

        while (Imovel::withTrashed()->where(function ($q) use ($id) {
            $q->where('id', $id)->orWhere('slug', $id);
        })->exists()) {
            $id = $base.'-'.$n;
            $n++;
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function atributos(array $dados, string $referencia): array
    {
        $base = [
            'referencia' => $referencia,
            'titulo' => $dados['titulo'],
            'tipologia' => $dados['tipologia'],
            'tipo' => $dados['tipo'],
            'zona' => $dados['zona'],
            'preco' => $dados['preco'],
            'area' => $dados['area'],
            'quartos' => $dados['quartos'],
            'casas_banho' => $dados['casasBanho'],
            'estado' => $dados['estado'],
            'resumo' => $dados['resumo'],
            'descricao' => $dados['descricao'],
            'destaques' => array_values($dados['destaques'] ?? []),
            'traducoes' => $dados['traducoes'] ?? [],
            'publicado' => (bool) ($dados['publicado'] ?? false),
        ];

        $opcionais = [
            'finalidade' => 'finalidade',
            'moeda' => 'moeda',
            'areaTotal' => 'area_total',
            'vagas' => 'vagas',
            'endereco' => 'endereco',
            'cidade' => 'cidade',
            'distrito' => 'distrito',
            'pais' => 'pais',
            'codigoPostal' => 'codigo_postal',
            'metaTitulo' => 'meta_titulo',
            'metaDescricao' => 'meta_descricao',
            'destaque' => 'destaque',
            'ordem' => 'ordem',
        ];

        foreach ($opcionais as $entrada => $coluna) {
            if (array_key_exists($entrada, $dados)) {
                $base[$coluna] = $dados[$entrada];
            }
        }

        return $base;
    }
}
