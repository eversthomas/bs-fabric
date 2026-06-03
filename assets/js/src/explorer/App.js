import { DataViews } from '@wordpress/dataviews/wp';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

function getConfig() {
    return window.bsFabricExplorer || {
        entries: [],
        templates: [],
        statusLabels: {},
        strings: {},
        ajaxUrl: '',
        entryNonce: '',
    };
}

function fetchExplorerRows(config, view) {
    const body = new URLSearchParams();
    body.set('action', 'bs_fabric_explorer_rows');
    body.set('nonce', config.entryNonce);
    body.set('page', String(view.page || 1));
    body.set('per_page', String(view.perPage || 20));
    body.set('search', view.search || '');
    body.set('sort_field', view.sort?.field || 'created_at');
    body.set('sort_direction', view.sort?.direction || 'desc');
    body.set('filters', JSON.stringify(view.filters || []));

    return fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
    }).then((response) => response.json());
}

function deleteEntry(item, config, onDeleted) {
    if (!window.confirm(config.strings.confirmDeleteEntry)) {
        return;
    }

    const body = new URLSearchParams();
    body.set('action', 'bs_fabric_delete_entry');
    body.set('nonce', config.entryNonce);
    body.set('entry_id', String(item.id));

    fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
    })
        .then((response) => response.json())
        .then((payload) => {
            if (!payload || !payload.success) {
                window.alert(config.strings.deleteError);
                return;
            }

            onDeleted();
        })
        .catch(() => {
            window.alert(config.strings.deleteError);
        });
}

function buildFields(config) {
    const templateElements = config.templates.map((template) => ({
        value: String(template.id),
        label: template.label,
    }));

    const statusElements = Object.entries(config.statusLabels).map(([value, label]) => ({
        value,
        label,
    }));

    return [
        {
            id: 'title',
            label: config.strings.title,
            enableGlobalSearch: true,
            enableSorting: true,
            render: ({ item }) => (
                <a href={item.editUrl}>{item.title}</a>
            ),
        },
        {
            id: 'template_id',
            label: config.strings.template,
            getValue: ({ item }) => item.template_label,
            elements: templateElements,
            filterBy: {
                operators: ['is', 'isNot', 'isAny', 'isNone'],
            },
            enableSorting: true,
            render: ({ item }) => item.template_label,
        },
        {
            id: 'status',
            label: config.strings.status,
            elements: statusElements,
            filterBy: {
                operators: ['is', 'isNot', 'isAny', 'isNone'],
            },
            enableSorting: true,
            render: ({ item }) => item.status_label,
        },
        {
            id: 'created_at',
            label: config.strings.createdAt,
            type: 'datetime',
            enableSorting: true,
            filterBy: {
                operators: ['before', 'after', 'on', 'notOn'],
            },
            render: ({ item }) => item.created_at_label,
            getValue: ({ item }) => item.created_at,
        },
    ];
}

const defaultLayouts = {
    table: {
        layout: {
            primaryField: 'title',
        },
    },
};

const App = () => {
    const config = getConfig();
    const fields = useMemo(() => buildFields(config), [config]);
    const [entries, setEntries] = useState([]);
    const [paginationInfo, setPaginationInfo] = useState({
        totalItems: 0,
        totalPages: 1,
    });
    const [view, setView] = useState({
        type: 'table',
        perPage: 20,
        page: 1,
        search: '',
        filters: [],
        sort: {
            field: 'created_at',
            direction: 'desc',
        },
        layout: defaultLayouts.table.layout,
        fields: ['title', 'template_id', 'status', 'created_at'],
    });

    const loadRows = useCallback(() => {
        fetchExplorerRows(config, view)
            .then((payload) => {
                if (!payload || !payload.success || !payload.data) {
                    window.alert(config.strings.loadError);
                    return;
                }

                setEntries(payload.data.entries || []);
                setPaginationInfo(payload.data.pagination || {
                    totalItems: 0,
                    totalPages: 1,
                });
            })
            .catch(() => {
                window.alert(config.strings.loadError);
            });
    }, [config, view]);

    useEffect(() => {
        loadRows();
    }, [loadRows]);

    const actions = useMemo(
        () => [
            {
                id: 'edit',
                label: config.strings.edit,
                isPrimary: true,
                callback: ([item]) => {
                    window.location.href = item.editUrl;
                },
            },
            {
                id: 'delete',
                label: config.strings.delete,
                callback: ([item]) => {
                    deleteEntry(item, config, loadRows);
                },
            },
        ],
        [config, loadRows]
    );

    return (
        <DataViews
            data={entries}
            fields={fields}
            view={view}
            onChangeView={setView}
            defaultLayouts={defaultLayouts}
            actions={actions}
            paginationInfo={paginationInfo}
            getItemId={(item) => String(item.id)}
            onClickItem={(item) => {
                window.location.href = item.editUrl;
            }}
            isItemClickable={() => true}
            searchLabel={config.strings.search}
        />
    );
};

export default App;
