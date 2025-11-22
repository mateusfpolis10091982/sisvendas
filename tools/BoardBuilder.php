<?php
namespace Tools;
class BoardBuilder {
    public static function getBoard(): array {
        return [
            'title' => 'Planejamento SisVendas',
            'sections' => [
                ['name' => 'Métricas', 'items' => ['prefeituras','orgaos','ssl_scans','oportunidades','auditorias']],
                ['name' => 'Função H', 'items' => ['scan dominio','classificar risco','gerar oportunidades','agenda follow-up']],
            ],
        ];
    }
    public static function toMarkdown(array $board): string {
        $out = '# ' . ($board['title'] ?? 'Board') . "\n\n";
        foreach (($board['sections'] ?? []) as $sec) {
            $out .= '## ' . ($sec['name'] ?? 'Seção') . "\n";
            foreach (($sec['items'] ?? []) as $item) { $out .= '- ' . $item . "\n"; }
            $out .= "\n";
        }
        return $out;
    }
}
?>
