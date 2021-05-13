import React, { useEffect, useState } from 'react'
import { withErrorBoundary, useErrorBoundary } from 'react-use-error-boundary'

const ErrorBoundary = withErrorBoundary(({ children }) => {
    const [error, resetError] = useErrorBoundary((error) => {
        console.log(error.message)
    })

    if (error) {
        return (
            <div>
                <p>{error.message}</p>
                <button onClick={resetError}>Try again</button>
            </div>
        )
    }

    return <div>{children}</div>
})

export default ErrorBoundary
