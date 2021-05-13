import React, { useRef, useState, useEffect } from 'react'
import GraphiQL from 'graphiql'
import { print, parse, validate } from 'graphql'
import compress from 'graphql-query-compress'
import 'graphiql/graphiql.css'
import {
    BooleanParam,
    StringParam,
    useQueryParam,
    withDefault,
} from 'use-query-params'

import './style.css'

let nonce =
    window.wpGraphiQLSettings && window.wpGraphiQLSettings.nonce
        ? window.wpGraphiQLSettings.nonce
        : null

let endpoint =
    window.wpGraphiQLSettings && window.wpGraphiQLSettings.graphqlEndpoint
        ? window.wpGraphiQLSettings.graphqlEndpoint
        : window.location.origin

let headers = {
    Accept: `application/json`,
    'Content-Type': `application/json`,
}

if (nonce) {
    headers = { ...headers, 'X-WP-Nonce': nonce }
}

export function fetcher(graphQLParams) {
    return fetch(endpoint, {
        method: `post`,
        headers,
        body: JSON.stringify(graphQLParams),
    }).then(function (response) {
        return response.json()
    })
}

const IDE = ({ schema }) => {
    const graphiql = useRef()

    const [query, setQueryInUrl] = useQueryParam('query', StringParam)

    console.log({ ideQuery: query })

    const [currentQuery, setCurrentQuery] = useState(null)
    const [isExplorerOpen, setExplorerOpen] = useQueryParam(
        'isExplorerOpen',
        withDefault(BooleanParam, true)
    )
    const [isExporterOpen, setExporterOpen] = useQueryParam(
        'isExporterOpen',
        withDefault(BooleanParam, false)
    )

    useEffect(() => {
        if (!currentQuery && query && query.length) {
            try {
                let parsedQuery = print(parse(query))
                setCurrentQuery(parsedQuery)
            } catch (e) {
                setCurrentQuery(query)
            }
        }
    }, [currentQuery, query])

    return (
        <div
            style={{
                display: `flex`,
                flex: 1,
            }}
        >
            <GraphiQL
                ref={(c) => (graphiql.current = c)}
                fetcher={fetcher}
                query={currentQuery}
                schema={schema}
                onEditQuery={(editedQuery) => {
                    setCurrentQuery(editedQuery)
                    if (query !== editedQuery) {
                        setQueryInUrl(compress(editedQuery))
                    }
                }}
            >
                <GraphiQL.Toolbar>
                    <GraphiQL.Button
                        onClick={() => graphiql.current.handlePrettifyQuery()}
                        label="Prettify"
                        title="Prettify Query (Shift-Ctrl-P)"
                    />
                    <GraphiQL.Button
                        onClick={() => graphiql.current.handleToggleHistory()}
                        label="History"
                        title="Toggle History Panel"
                    />
                    <GraphiQL.Button
                        onClick={() => {
                            setExplorerOpen(!isExplorerOpen)
                        }}
                        label="Explorer"
                        title="Toggle Explorer Panel"
                    />
                    <GraphiQL.Button
                        onClick={() => {
                            setExporterOpen(!isExporterOpen)
                        }}
                        label="Code Exporter"
                        title="Toggle Code Exporter Panel"
                    />
                </GraphiQL.Toolbar>
            </GraphiQL>
        </div>
    )
}

export default IDE
