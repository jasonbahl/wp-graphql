import React from 'react'
import 'graphiql-code-exporter/CodeExporter.css'
import { BooleanParam, useQueryParam, withDefault } from 'use-query-params'

const Exporter = () => {
    const [isOpen, setIsOpen] = useQueryParam(
        'isExporterOpen',
        withDefault(BooleanParam, false)
    )
    if (!isOpen) {
        return null
    }

    return <h2>Exporter</h2>
}

export default Exporter
