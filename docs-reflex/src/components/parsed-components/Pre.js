import React from "react"
import { chakra } from "@chakra-ui/core"
import CodeBlock from '../codeblock/codeblock'

export const Pre = props => {
    const { children } = props;

    console.log( props );

    const code = children && children[0].props && children[0].props.children && children[0].props.children[0] ? children[0].props.children[0] : null;
    return code ? (
        <chakra.div position="relative" {...props}>
            <CodeBlock live={true} {...props}>{code}</CodeBlock>
        </chakra.div>
    ) : null;
};
