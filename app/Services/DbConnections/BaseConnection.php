<?php

namespace App\Services\DbConnections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class BaseConnection
{
    /**
     * Classe do Model associada.
     * Deve ser definida em cada classe filha.
     */
    protected string $modelClass;

    /**
     * Cria uma nova instância de query limpa.
     */
    protected function newQuery()
    {
        return app($this->modelClass)->newQuery();
    }


    // ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   FUNÇÕES DE BUSCA   ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   

    /**
     * Retorna um registro pelo ID.
     */
    public function getById(int $id): ?Model
    {
        return $this->newQuery()
            ->where('id', $id)
            ->first();
    }

    /**
     * Retorna todos os registros do model.
     */
    public function getAll(): Collection
    {
        return $this->newQuery()->get();
    }

    /**
     * Busca um registro por coluna e valor.
     */
    public function findBy(string $column, mixed $value): ?Model
    {
        return $this->newQuery()
            ->where($column, $value)
            ->first();
    }

    /**
     * Busca múltiplos registros por coluna e valor.
     */
    public function findAllBy(string $column, mixed $value): Collection
    {
        return $this->newQuery()
            ->where($column, $value)
            ->get();
    }

    /**
     * Busca um registro por colunas e valores..
     */
    public function findByConditions(array $conditions): ?Model
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Busca um registro por colunas e valores com a condição OR..
     */
    public function findByOrConditions(array $conditions): ?Model
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            $query->orWhere($column, $value);
        }

        return $query->first();
    }

    /**
     * Busca os registros por colunas e valores..
     */
    public function findAllByConditions(array $conditions)
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get();
    }

    /**
     * Busca registros por termo parcial (LIKE).
     */
    public function search(string $column, string $term): Collection
    {
        return $this->newQuery()
            ->where($column, 'like', "%{$term}%")
            ->get();
    }

    /**
     * Retorna uma lista paginada.
     */
    public function paginate(int $perPage = 15, string $orderBy = 'id', string $direction = 'desc')
    {
        return $this->newQuery()
            ->orderBy($orderBy, $direction)
            ->paginate($perPage);
    }

    /**
     * Retorna registros criados recentemente.
     */
    public function getRecent(int $minutes = 60): Collection
    {
        return $this->newQuery()
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->get();
    }

    /**
     * Retorna o ultimo registro
     */
    public function getLast($column, $term) {
        return $this->newQuery()
            ->where($column, $term)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Retorna um registro com base na pesquisa de um campo JSON, informando o valor a ser pesquisado nesse campo.
     * EXEMPLOS: 
     * -----------------------------------------------------------------
     * product_group = [1,2,3]
     * $user = $this->findByJson('product_group', 2);
     * -----------------------------------------------------------------
     * data_json = {"product_group": [{"id": 1}, {"id": 2}]}
     * $user = $this->findByJson('data_json.product_group.id', 2);
     * -----------------------------------------------------------------
     * $this->findByJson('data_json.product_group.id', 2);  aqui procura o valor 2 dentro do json, chaves product_group e id
     */
    public function findByJson(string $column, mixed $value)
    {
        // Exemplo de entrada: "data_json.product_group.id"
        [$field, $path] = array_pad(explode('.', $column, 2), 2, null);

        // Se houver caminho (ex: product_group.id)
        if ($path) {
            // Se o caminho tem múltiplos níveis, adiciona [*] automaticamente
            // Exemplo: product_group.id → $.product_group[*].id
            $parts = explode('.', $path);
            $jsonPath = '$.' . implode('[*].', $parts);
        } else {
            // Sem subcaminho, pesquisa no JSON raiz
            $jsonPath = '$';
        }

        return $this->newQuery()
            ->whereRaw("JSON_CONTAINS($field, ?, ?)", [json_encode($value), $jsonPath])
            ->first();
    }

    /**
     * Retorna vários registros com base na pesquisa de um campo JSON, informando o valor a ser pesquisado nesse campo.
     * EXEMPLOS: 
     * -----------------------------------------------------------------
     * product_group = [1,2,3]
     * $user = $this->findByJson('product_group', 2);
     * -----------------------------------------------------------------
     * data_json = {"product_group": [{"id": 1}, {"id": 2}]}
     * $user = $this->findByJson('data_json.product_group.id', 2);
     * -----------------------------------------------------------------
     * $this->findByJson('data_json.product_group.id', 2);  aqui procura o valor 2 dentro do json, chaves product_group e id
     */
    public function findAllByJson(string $column, mixed $value, int $perPage = 50, int $page = 1)
    {
        // Exemplo de entrada: "data_json.product_group.id"
        [$field, $path] = array_pad(explode('.', $column, 2), 2, null);

        // Se houver caminho (ex: product_group.id)
        if ($path) {
            // Se o caminho tem múltiplos níveis, adiciona [*] automaticamente
            // Exemplo: product_group.id → $.product_group[*].id
            $parts = explode('.', $path);
            $jsonPath = '$.' . implode('[*].', $parts);
        } else {
            // Sem subcaminho, pesquisa no JSON raiz
            $jsonPath = '$';
        }

        return $this->newQuery()
            ->whereRaw("JSON_CONTAINS($field, ?, ?)", [json_encode($value), $jsonPath])
            ->paginate($perPage, ['*'], 'page', $page);;
    }

    // ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   FUNÇÕES DE CRIAÇÃO   ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   

    /**
     * Cria um novo registro.
     */
    public function create(array $data): Model
    {
        return $this->newQuery()->create($data);
    }

    /**
     * Cria ou retorna o primeiro registro que corresponde às condições.
     */
    public function firstOrCreate(array $conditions, array $values = []): Model
    {
        return $this->newQuery()->firstOrCreate($conditions, $values);
    }

    /**
     * Cria um registro e retorna o ID.
     */
    public function insertGetId(array $data)
    {
        return $this->newQuery()
            ->insertGetId($data);
    }

    public function insertOrIgnore(array $values): int
    {
        return $this->newQuery()->insertOrIgnore($values);
    }


    // ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   FUNÇÕES DE ATUALIZAÇÃO   ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   
    /**
     * Inserção ou atualização múltipla.
     */
    public function upsert(array $data, array $uniqueBy, array $update = [])
    {
        return $this->newQuery()->upsert($data, $uniqueBy, $update);
    }

    /**
     * Atualiza um registro pelo ID.
     */
    public function update(int $id, array $data): int
    {
        return $this->newQuery()
            ->where('id', $id)
            ->update($data);
    }


    /**
     * Atualiza registros com base em múltiplas condições.
     */
    public function updateWhere(array $conditions, array $data): int
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->update($data);
    }


    /**
     * Exclui um registro pelo ID.
     */
    public function delete(int $id): int
    {
        return $this->newQuery()
            ->where('id', $id)
            ->delete();
    }

    /*
    * Excluir registros com base em múltiplas condições.
    */
    public function deleteWhere(array $conditions): int
    {
        $query = $this->newQuery();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->delete();
    }


    /*
    * Função para inser um dado num campo json
    * Exemplo:
    * appendJsonValueForMany('Nome do Campo', [array_com_ids], "dado a inserir");
    * appendJsonValueForMany('product_group', [1, 2, 3], 4);
    */
    public function updateJsonValueForMany(string $field, array $ids, mixed $value, bool $action = true): int
    {
        $updated = 0;

        $records = $this->newQuery()
            ->whereIn('id', $ids)
            ->get(['id', $field]);

        foreach ($records as $record) {
            $data = json_decode($record->{$field}, true) ?? [];

            if ($action) {
                // Adiciona o valor se não existir
                if (!in_array($value, $data)) {
                    $data[] = $value;
                } else {
                    continue; // já existe, não precisa atualizar
                }
            } else {
                // Remove o valor se existir
                $index = array_search($value, $data);
                if ($index !== false) {
                    unset($data[$index]);
                    $data = array_values($data); // reindexa o array
                } else {
                    continue; // não existia, nada pra fazer
                }
            }

            $this->newQuery()
                ->where('id', $record->id)
                ->update([
                    $field => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

            $updated++;
        }

        return $updated; // quantidade de registros alterados
    }

    // ###   ###   ###   ###   ###   ###   ###   ###   ###   ###   FUNÇÕES ESPECIAIS   ###   ###   ###   ###   ###   ###   ###   ###   ###   ###  
    /*
     * Conta o numero de registros.
     */
    public function count(string $column, mixed $value): int
    {
        return $this->newQuery()
            ->where($column, $value)
            ->count();
    }
}
