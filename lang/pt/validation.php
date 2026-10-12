<?php

/*
| Mensagens de validação em português de Portugal.
|
| Os FormRequests têm mensagens próprias para os campos do painel; estas são
| a rede de segurança para qualquer regra sem mensagem própria. Sem este
| ficheiro (APP_LOCALE=pt) o Laravel devolvia a chave crua, ex. "validation.in".
| :attribute usa os nomes da lista `attributes` no fim.
*/

return [
    'accepted' => 'O campo :attribute tem de ser aceite.',
    'array' => 'O campo :attribute tem de ser uma lista.',
    'boolean' => 'O campo :attribute tem de ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não coincide.',
    'date' => 'O campo :attribute não é uma data válida.',
    'email' => 'Indique um e-mail válido.',
    'exists' => 'O valor de :attribute não existe.',
    'file' => 'O campo :attribute tem de ser um ficheiro.',
    'image' => 'O campo :attribute tem de ser uma imagem.',
    'in' => 'O valor de :attribute é inválido. Escolha uma das opções.',
    'integer' => 'O campo :attribute tem de ser um número inteiro.',
    'json' => 'O campo :attribute tem de ser JSON válido.',
    'max' => [
        'array' => 'O campo :attribute pode ter no máximo :max itens.',
        'file' => 'O ficheiro :attribute pode ter no máximo :max KB.',
        'numeric' => 'O campo :attribute não pode passar de :max.',
        'string' => 'O campo :attribute pode ter no máximo :max caracteres.',
    ],
    'mimes' => 'O ficheiro :attribute tem de ser do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute precisa de pelo menos :min itens.',
        'file' => 'O ficheiro :attribute precisa de pelo menos :min KB.',
        'numeric' => 'O campo :attribute tem de ser pelo menos :min.',
        'string' => 'O campo :attribute precisa de pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute tem de ser um número.',
    'regex' => 'O formato de :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'size' => [
        'array' => 'O campo :attribute tem de ter :size itens.',
        'file' => 'O ficheiro :attribute tem de ter :size KB.',
        'numeric' => 'O campo :attribute tem de ser :size.',
        'string' => 'O campo :attribute tem de ter :size caracteres.',
    ],
    'string' => 'O campo :attribute tem de ser um texto.',
    'unique' => 'Já existe um registo com este :attribute.',
    'url' => 'O campo :attribute tem de ser um endereço válido.',

    'attributes' => [
        'referencia' => 'referência',
        'titulo' => 'título',
        'tipologia' => 'tipologia',
        'tipo' => 'tipo de imóvel',
        'finalidade' => 'finalidade',
        'moeda' => 'moeda',
        'zona' => 'zona',
        'preco' => 'preço',
        'area' => 'área útil',
        'areaTotal' => 'área total',
        'quartos' => 'quartos',
        'casasBanho' => 'casas de banho',
        'vagas' => 'lugares de garagem',
        'estado' => 'estado',
        'resumo' => 'resumo',
        'descricao' => 'descrição',
        'destaques' => 'destaques',
        'imagens' => 'fotos',
        'publicado' => 'publicado',
        'endereco' => 'morada',
        'cidade' => 'cidade',
        'distrito' => 'distrito',
        'pais' => 'país',
        'codigoPostal' => 'código postal',
        'metaTitulo' => 'título SEO',
        'metaDescricao' => 'descrição SEO',
        'destaque' => 'destaque',
        'ordem' => 'ordem',
        'nome' => 'nome',
        'email' => 'e-mail',
        'senha' => 'senha',
        'telefone' => 'telefone',
        'mensagem' => 'mensagem',
        'foto' => 'foto',
    ],
];
