import React, { useState, useEffect } from 'react'
import { BrowserRouter as Router, Route } from 'react-router-dom'
import {
    QueryParamProvider,
    useQueryParams,
    StringParam,
    ObjectParam,
    BooleanParam,
    withDefault,
    useQueryParam,
} from 'use-query-params'
import Explorer from './components/Explorer'
import IDE from './components/IDE'
import Exporter from './components/Exporter'
import { fetcher } from './components/IDE'
import { buildClientSchema, getIntrospectionQuery } from 'graphql'
import 'graphiql/graphiql.css'
import './app.css'

const AppWithRouter = () => {
    const [queryParams, setQueryParams] = useQueryParams({
        query: withDefault(StringParam, ''),
        variables: ObjectParam,
        isExplorerOpen: withDefault(BooleanParam, true),
        isExporterOpen: withDefault(BooleanParam, false),
    })

    const [query, setQuery] = useQueryParam('query', StringParam)
    const [schema, setSchema] = useState(null)

    useEffect(() => {
        if (!schema) {
            fetcher({
                query: getIntrospectionQuery(),
            }).then((result) => {
                const fetchedSchema = buildClientSchema(result.data)
                setSchema(fetchedSchema)
            })
        }
    })

    return (
        <div className="graphiql-container">
            <Explorer
                query={query}
                schema={schema}
                onEdit={() => {
                    console.log('edit explorer')
                }}
                onRunOperation={() => {
                    console.log('run operation')
                }}
            />
            <IDE
                schema={schema}
                query={query}
                setQuery={setQuery}
                setQueryInUrl={setQuery}
            />
            <Exporter />
        </div>
    )
}

const App = () => (
    <Router>
        <QueryParamProvider ReactRouterRoute={Route}>
            <AppWithRouter />
        </QueryParamProvider>
    </Router>
)

export default App
