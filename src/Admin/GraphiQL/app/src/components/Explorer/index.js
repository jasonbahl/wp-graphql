import React, { useState, useEffect } from 'react'
import { Button, Input, Select } from 'antd'
import {
    GraphQLObjectType,
    GraphQLScalarType,
    GraphQLSchema,
    isWrappingType,
    parse,
} from 'graphql'
import { Tree } from 'antd'
import {
    BooleanParam,
    StringParam,
    useQueryParam,
    withDefault,
} from 'use-query-params'
import { getParsedQuery } from '../../utils/utils'
import ErrorBoundary from './ErrorBoundary'

const QueryForm = ({ operationTypes }) => {
    if (!operationTypes.length) {
        return null
    }

    return (
        <div id="docs-explorer-buttons">
            {operationTypes.map((type) => (
                <Button
                    key={`Add${type}Button`}
                    onClick={() => {
                        alert(`Add ${type}`)
                    }}
                    block
                    style={{ marginBottom: '12px' }}
                >{`Add ${type}`}</Button>
            ))}
        </div>
    )
}

const renderTreeNodes = (treeData) => {
    if (!treeData || !treeData.length) {
        return null
    }

    return treeData.map((node) => {
        let title = node.title
        switch (node.type) {
            case 'enum':
                title = (
                    <Select
                        defaultValue="lucy"
                        style={{ width: 120 }}
                        onChange={() => {
                            console.log('change nested select! ')
                        }}
                    >
                        <Select.Option value="jack">Jack</Select.Option>
                        <Select.Option value="lucy">Lucy</Select.Option>
                        <Select.Option value="disabled" disabled>
                            Disabled
                        </Select.Option>
                        <Select.Option value="Yiminghe">yiminghe</Select.Option>
                    </Select>
                )
                break
            case 'string':
                title = <Input value={node.title} />
                break
            default:
                break
        }

        return (
            <Tree.TreeNode title={title} key={node.key} dataRef={node}>
                {node.treeData ? renderTreeNodes(node.treeData) : null}
            </Tree.TreeNode>
        )
    })
}

let memoizedParsedQuery = []
export const memoizeAndParseQuery = (query) => {

    if ( ! query || '' === query ) {
        memoizedParsedQuery[1] = '';
        return null;
    }

    if (memoizedParsedQuery.length && memoizedParsedQuery[0] === query) {
        return memoizedParsedQuery[1]
    }

    let parsed = parseQuery(query)

    if (parsed instanceof Error) {
        if (memoizedParsedQuery) {
            return memoizedParsedQuery[1]
        }
        return ''
    }

    memoizedParsedQuery = [query, parsed]
    return parsed
}

const parseQuery = (query) => {
    try {
        if (!query.trim()) {
            return null
        }
        return parse(query, { noLocation: true })
    } catch (e) {
        return new Error(e)
    }
}

const getFieldsAsTree = ( type ) => {

    let treeFields = []

    if ( ! type instanceof GraphQLObjectType ) {
        return treeFields;
    }

    if ( ! 'getFields' in type ) {
        return treeFields;
    }

    const fieldMap = type.getFields()
    const fields = Object.keys(fieldMap).map((name) => fieldMap[name])

    console.log( { type, fields } );

    if ( ! fields.length ) {
        return treeFields;
    }

    fields.map((field, i) => {

        console.log( field )

        let fieldType = 'String';

        if ( field && field.type ) {
            if ( isWrappingType( field.type ) ) {
                fieldType = field.type.ofType;
            } else {
                fieldType = field.type;
            }
        }

        const fieldData = {
            title: field.name,
            key: type.name + '.' + field.name,
            isLeaf: fieldType instanceof GraphQLScalarType,
            fieldType: fieldType?.name ?? 'String',
        }
        treeFields.push(fieldData)
    })

    return treeFields;

}

const updateTreeData = ( fields, key, schema ) => {
    return fields.map(( field ) => {
        if ( field.key === key ) {
            console.log(field)
            const fieldType = schema.getType( field.fieldType )
            const children = getFieldsAsTree( fieldType );
            if ( ! children.length ) {
                return field;
            }
            return { ...field, children }
        }
        if ( field.children ) {
            return { ...field, children: updateTreeData(field.children, key, schema)}
        }
        return field;
    })
}

const QueryBuilderFieldSelect = ({ type, operation, schema }) => {
    const [treeData, setTreeData] = useState(null)

    useEffect(() => {

        const treeFields = getFieldsAsTree( type )

        if (treeFields && treeFields.length) {
            if (null === treeData) {
                setTreeData(treeFields)
            }
        }
    })

    const onLoadData = ({ key, children }) => {

        console.log( { onLoadData: { key, children }})

        return new Promise((resolve) => {
            if (children) {
                console.log({ loadData: { key, children } })
                resolve()
            }

            setTreeData((origin) => {
                return updateTreeData(origin, key, schema)
            })
            resolve()
        })
    }

    if (type instanceof GraphQLScalarType) {
        return <div>Scalar: {type.name}</div>
    }

    if (!type || !'getFields' in type) {
        return null
    }

    return (
        <Tree.DirectoryTree
            multiple
            checkable
            showIcon={false}
            treeData={treeData}
            loadData={onLoadData}
            setTreeData={setTreeData}
        />
    )
}

/**
 * The Checkbox interface for building the query
 *
 * @param schema
 * @param query
 * @param parsedQuery
 * @param queryDefinition
 * @returns {null}
 * @constructor
 */
const QueryBuilder = ({ schema, query, parsedQuery, queryDefinition }) => {
    if (!queryDefinition.operation) {
        return null
    }

    let type

    switch (queryDefinition.operation) {
        case 'subscription':
            type = schema.getSubscriptionType()
            break
        case 'mutation':
            type = schema.getMutationType()
            break
        case 'query':
        default:
            type = schema.getQueryType()
            break
    }

    if ( ! type ) {
        return null
    }

    return (
        <div>
            <h3>{queryDefinition.operation}</h3>
            <QueryBuilderFieldSelect type={type} schema={schema} />
        </div>
    )
}

const ExplorerWrapper = ({ schema, children }) => {
    const [isOpen, setIsOpen] = useQueryParam(
        'isExplorerOpen',
        withDefault(BooleanParam, true)
    )
    const [width, setWidth] = useState('300px')
    const [title, setTitle] = useState('Explorer')

    return (
        <div
            className="docExplorerWrap"
            style={{
                height: '100%',
                width: width,
                minWidth: width,
                zIndex: 7,
                display: false === isOpen ? 'none' : 'flex',
                flexDirection: 'column',
                overflow: 'hidden',
            }}
        >
            <div className="doc-explorer-title-bar">
                <div className="doc-explorer-title">{title}</div>
                <div className="doc-explorer-rhs">
                    <div
                        className="docExplorerHide"
                        onClick={() => {
                            setIsOpen(!isOpen)
                        }}
                    >
                        {'\u2715'}
                    </div>
                </div>
            </div>
            { ! schema ? <div className="error-container">Loading Schema...</div> : children}
        </div>
    )
}

const Explorer = ({ schema, query }) => {
    const [operationTypes, setOperationTypes] = useState([])
    const [contentsOffset, setContentsOffset] = useState('100px')

    useEffect(() => {
        const buttonWrapper = document.getElementById('docs-explorer-buttons')
        if (buttonWrapper) {
            const bounds = buttonWrapper.getBoundingClientRect()
            setContentsOffset(`${bounds.bottom}px`)
        }
    })

    let parsedQuery = query ? memoizeAndParseQuery(query) : null;

    if (!operationTypes.length && schema instanceof GraphQLSchema) {
        let schemaOperationTypes = []

        if (schema.getQueryType()) {
            schemaOperationTypes.push('Query')
        }
        if (schema.getMutationType()) {
            schemaOperationTypes.push('Mutation')
        }
        if (schema.getSubscriptionType()) {
            schemaOperationTypes.push('Subscription')
        }
        if (schemaOperationTypes.length) {
            setOperationTypes(schemaOperationTypes)
        }
    }

    return (
        <ExplorerWrapper schema={schema} >
            <ErrorBoundary>
                <div style={{ padding: '10px' }}>
                    <QueryForm operationTypes={operationTypes} />
                    <div
                        className="doc-explorer-contents"
                        style={{
                            top: contentsOffset,
                        }}
                    >
                        {parsedQuery &&
                            parsedQuery.definitions &&
                            parsedQuery.definitions.length &&
                            parsedQuery.definitions.map(
                                (queryDefinition, i) => (
                                    <QueryBuilder
                                        key={i}
                                        schema={schema}
                                        query={query}
                                        parsedQuery={parsedQuery}
                                        queryDefinition={queryDefinition}
                                    />
                                )
                            )}
                    </div>
                </div>
            </ErrorBoundary>
        </ExplorerWrapper>
    )
}

export default Explorer
