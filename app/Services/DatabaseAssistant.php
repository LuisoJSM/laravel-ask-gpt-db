<?php

namespace App\Services;

use App\Enums\OpenAiModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use OpenAI\Laravel\Facades\OpenAI;

class DatabaseAssistant
{
    /**
     * Get database schema information.
     *
     * @return array<string, mixed>
     */
    protected function getDatabaseSchema(): array
    {
        $tables = [];

        // Get all tables in the database
        $databaseTables = DB::select('SHOW TABLES');

        // The property name depends on the database driver
        $tableNameProperty = 'Tables_in_' . config('database.connections.mysql.database');

        foreach ($databaseTables as $table)
        {
            // table => {"Tables_in_laravel":"cache"}
            $tableName = $table->$tableNameProperty;

            // Skip laravel tables
            if (
                $tableName === 'cache' ||
                $tableName === 'cache_locks' ||
                $tableName === 'migrations' ||
                $tableName === 'jobs' ||
                $tableName === 'job_batches' ||
                $tableName === 'failed_jobs' ||
                $tableName === 'password_reset_tokens' ||
                $tableName === 'sessions'
            ) continue;

            $columns = Schema::getColumnListing($tableName);
            $columnDetails = [];

            foreach ($columns as $column) {
                $type = Schema::getColumnType($tableName, $column);
                $columnDetails[] = [
                    'name' => $column,
                    'type' => $type,
                ];
            }

            $tables[$tableName] = [
                'columns' => $columnDetails,
            ];

            // Add foreign keys information
            $foreignKeys = [];

            try {
                $foreignKeysList = DB::select(DB::raw(
                    "SELECT
                            '$tableName' as table_name,
                            kcu.COLUMN_NAME as column_name,
                            kcu.REFERENCED_TABLE_NAME AS foreign_table_name,
                            kcu.REFERENCED_COLUMN_NAME AS foreign_column_name
                        FROM
                            information_schema.KEY_COLUMN_USAGE kcu
                        WHERE
                            kcu.CONSTRAINT_SCHEMA = DATABASE() AND
                            kcu.TABLE_NAME = '$tableName' AND
                            kcu.REFERENCED_TABLE_NAME IS NOT NULL"
                )->getValue(DB::getQueryGrammar()));

                foreach ($foreignKeysList as $foreignKey) {
                    $foreignKeys[] = [
                        'column' => $foreignKey->column_name,
                        'references' => $foreignKey->foreign_table_name . '.' . $foreignKey->foreign_column_name,
                    ];
                }
            } catch (\Exception) {
                // On error, we silently continue without foreign key information
            }

            $tables[$tableName]['foreign_keys'] = $foreignKeys;
        }

        Log::info(json_encode($tables));

        return $tables;
    }

    /**
     * Generate SQL query from natural language using OpenAI API.
     *
     * @param string $question The natural language question
     * @return string Generated SQL query
     */
    protected function generateSqlQuery(string $question): string
    {
        $schema = $this->getDatabaseSchema();

        $prompt = $this->buildPrompt($question, $schema);

        /**
         * Generates a SQL query from natural language using the OpenAI API.
         *
         * This method sends a request to OpenAI to convert a natural language question
         * into an executable SQL query based on the database schema.
         */
        $response = OpenAI::chat()->create([
            // Specifies the OpenAI model to use - gpt-4o is more accurate for technical tasks
            'model' => OpenAiModel::GPT4O->value,

            // Defines the context and query as a series of messages in chat format
            'messages' => [
                [
                    // The system message sets the behavior and instructions for the model
                    'role' => 'system',
                    'content' => 'Eres un experto en SQL especializado en generar consultas precisas EXCLUSIVAMENTE para MySQL 8.0.
                    RESTRICCIONES CRÍTICAS:
                    - NUNCA uses PERCENTILE_CONT, PERCENTILE_DISC ni WITHIN GROUP - estas funciones NO existen en MySQL 8.0
                    - NUNCA uses características específicas de PostgreSQL o SQL Server
                    - Para percentiles o distribuciones, usa ORDER BY con LIMIT o variables de usuario
                    - Las CTEs (WITH) están disponibles en MySQL 8.0, pero úsalas con sintaxis compatible

                    Dado el esquema de la base de datos:
                    1. Verifica SIEMPRE que cada función y sintaxis que uses sea 100% compatible con MySQL 8.0
                    2. Para agrupaciones por percentiles, usa subconsultas y aproximaciones con NTILE() o posiciones relativas
                    3. Analiza cuidadosamente la pregunta para entender los marcos temporales, condiciones y métricas
                    4. Presta especial atención a consultas temporales y usa funciones de fecha MySQL compatibles
                    5. Retorna únicamente la consulta SQL ejecutable sin explicaciones ni comentarios'
                ],
                [
                    // The user's message contains the question and the database schema
                    'role' => 'user',

                    'content' => $prompt,
                ],
            ],

            /**
             * Las CTEs (Common Table Expressions) o Expresiones de Tabla Común son una característica de SQL que
             * permite definir consultas temporales con nombre dentro de una consulta más grande.
             * En MySQL, se implementan usando la cláusula WITH.
             */

            // Parameters that control text generation:

            // Temperature: 0.1 (very low) favors deterministic and accurate responses
            // Low values (0.0-0.2) are ideal for technical tasks such as generating SQL
            'temperature' => 0.1,

            // top_p: 0.95 (nucleous sampling) limits the considered options to the 95% most likely
            // This setting allows some flexibility while maintaining high accuracy
            // Prevents the model from considering unlikely tokens that could introduce errors
            'top_p' => 0.95,
        ]);

        $sqlQuery = trim($response->choices[0]->message->content);

        // Remove Markdown code blocks if present
        return preg_replace('/^```sql\s*|\s*```$/i', '', $sqlQuery);
    }

    /**
     * Build the prompt for the OpenAI API.
     *
     * @param string $question The natural language question
     * @param array<string, mixed> $schema The database schema
     * @return string The complete prompt
     */
    protected function buildPrompt(string $question, array $schema): string
    {
        $schemaDescription = "Database Schema:\n";

        foreach ($schema as $table => $details) {
            $schemaDescription .= "Table: $table\n";
            $schemaDescription .= "Columns:\n";

            foreach ($details['columns'] as $column) {
                $schemaDescription .= "- {$column['name']} ({$column['type']})\n";
            }

            if (!empty($details['foreign_keys'])) {
                $schemaDescription .= "Foreign Keys:\n";
                foreach ($details['foreign_keys'] as $fk) {
                    $schemaDescription .= "- {$fk['column']} -> {$fk['references']}\n";
                }
            }

            $schemaDescription .= "\n";
        }

        // Add examples of question-SQL pairs to improve accuracy
        $examples = "
            Ejemplos:

            Question: \"¿Cuántos usuarios tenemos en total?\"
            SQL Query: SELECT COUNT(*) FROM users;

            Question: \"¿Cuántos pedidos tenemos programados para mañana?\"
            SQL Query: SELECT COUNT(*) FROM orders WHERE delivery_date = CURDATE() + INTERVAL 1 DAY;

            Question: \"¿Cuántos formularios de contacto no han sido respondidos?\"
            SQL Query: SELECT COUNT(*) FROM contact_forms WHERE is_responded = 0;

            Question: \"¿Cuántos pedidos se entregaron en los últimos 7 días?\"
            SQL Query: SELECT COUNT(*) FROM orders WHERE status = 'completed' AND delivery_date BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE();

            Question: \"¿Cuántos ingresos generamos el último trimestre?\"
            SQL Query: SELECT SUM(total_amount) FROM orders WHERE created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND CURDATE();

            Question: \"¿Quién es el usuario que más pedidos ha realizado?\"
            SQL Query: SELECT users.name, COUNT(orders.id) as order_count FROM users JOIN orders ON users.id = orders.user_id GROUP BY users.id ORDER BY order_count DESC LIMIT 1;
            ";

        return $schemaDescription . $examples . "\nQuestion: $question\n\nSQL Query:";
    }

    /**
     * Execute the generated SQL query and return the results.
     *
     * @param string $sqlQuery The SQL query to execute
     * @return array The query results
     */
    protected function executeQuery(string $sqlQuery): array
    {
        return DB::select($sqlQuery);
    }

    /**
     * Process a natural language question and return the answer.
     *
     * @param string $question The natural language question
     * @return array<string, mixed> The result with query and data
     */
    public function ask(string $question): array
    {
        $sqlQuery = $this->generateSqlQuery($question);
        $results = $this->executeQuery($sqlQuery);

        return [
            'question' => $question,
            'sql_query' => $sqlQuery,
            'results' => $results,
        ];
    }
}
