import { parse, print } from 'graphql'

/**
 * Given a string, if a valid GraphQL document, it returns it
 * printed and parsed as a formatted GraphQL string,
 * else returns original string
 *
 * @param string query The query string to parse
 * @returns {string}
 */
export const getParsedQuery = (query) => {
    let parsed = query
    try {
        parsed = print(parse(query))
    } catch (e) {
        // Do something with the error?
    }
    return parsed
}
