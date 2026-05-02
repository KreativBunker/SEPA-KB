<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ContractTemplateFieldRepository;
use App\Repositories\ContractTemplateRepository;
use App\Support\Auth;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class ContractTemplatesController
{
    public function index(): void
    {
        $repo = new ContractTemplateRepository();
        $items = $repo->all();

        View::render('contract_templates/index', [
            'items' => $items,
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        View::render('contract_templates/form', [
            'template' => null,
            'fields' => [],
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contract-templates/create'));
            exit;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $includeSepa = isset($_POST['include_sepa']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $body === '') {
            Flash::add('error', 'Titel und Vertragstext sind Pflichtfelder.');
            header('Location: ' . App::url('/contract-templates/create'));
            exit;
        }

        $user = Auth::user();
        $repo = new ContractTemplateRepository();
        $id = $repo->create([
            'title' => $title,
            'body' => $body,
            'include_sepa' => $includeSepa,
            'is_active' => $isActive,
            'created_by' => $user ? (int)$user['id'] : null,
        ]);

        (new ContractTemplateFieldRepository())->replaceForTemplate($id, $this->parseFieldsInput($_POST));

        Flash::add('success', 'Vorlage erstellt.');
        header('Location: ' . App::url('/contract-templates'));
        exit;
    }

    public function edit(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new ContractTemplateRepository();
        $template = $repo->find($id);

        if (!$template) {
            Flash::add('error', 'Vorlage nicht gefunden.');
            header('Location: ' . App::url('/contract-templates'));
            exit;
        }

        $fields = (new ContractTemplateFieldRepository())->forTemplate($id);

        View::render('contract_templates/form', [
            'template' => $template,
            'fields' => $fields,
            'csrf' => Csrf::token(),
        ]);
    }

    public function update(array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contract-templates/' . $id . '/edit'));
            exit;
        }

        $repo = new ContractTemplateRepository();
        $template = $repo->find($id);
        if (!$template) {
            Flash::add('error', 'Vorlage nicht gefunden.');
            header('Location: ' . App::url('/contract-templates'));
            exit;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $includeSepa = isset($_POST['include_sepa']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $body === '') {
            Flash::add('error', 'Titel und Vertragstext sind Pflichtfelder.');
            header('Location: ' . App::url('/contract-templates/' . $id . '/edit'));
            exit;
        }

        $repo->update($id, [
            'title' => $title,
            'body' => $body,
            'include_sepa' => $includeSepa,
            'is_active' => $isActive,
        ]);

        (new ContractTemplateFieldRepository())->replaceForTemplate($id, $this->parseFieldsInput($_POST));

        Flash::add('success', 'Vorlage aktualisiert.');
        header('Location: ' . App::url('/contract-templates'));
        exit;
    }

    public function delete(array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contract-templates'));
            exit;
        }

        $repo = new ContractTemplateRepository();
        $repo->delete($id);
        (new ContractTemplateFieldRepository())->deleteForTemplate($id);

        Flash::add('success', 'Vorlage gelöscht.');
        header('Location: ' . App::url('/contract-templates'));
        exit;
    }

    /**
     * Parse the parallel arrays posted by the dynamic field editor.
     * Expected POST keys: field_keys[], field_labels[], field_types[],
     * field_fill_by[], field_required[<index>], field_defaults[].
     */
    private function parseFieldsInput(array $post): array
    {
        $keys = (array)($post['field_keys'] ?? []);
        $labels = (array)($post['field_labels'] ?? []);
        $types = (array)($post['field_types'] ?? []);
        $fillBy = (array)($post['field_fill_by'] ?? []);
        $required = (array)($post['field_required'] ?? []);
        $defaults = (array)($post['field_defaults'] ?? []);

        $out = [];
        $count = max(count($keys), count($labels));
        for ($i = 0; $i < $count; $i++) {
            $key = (string)($keys[$i] ?? '');
            $label = (string)($labels[$i] ?? '');
            if (trim($key) === '' && trim($label) === '') {
                continue;
            }
            $out[] = [
                'field_key' => $key,
                'label' => $label,
                'field_type' => (string)($types[$i] ?? 'text'),
                'fill_by' => (string)($fillBy[$i] ?? 'admin'),
                'required' => isset($required[$i]) ? (string)$required[$i] === '1' : false,
                'default_value' => (string)($defaults[$i] ?? ''),
            ];
        }
        return $out;
    }
}
