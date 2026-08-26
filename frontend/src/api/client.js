import axios from 'axios'

const client = axios.create({
  baseURL: 'http://localhost',
  withCredentials: true,
  withXSRFToken: true,
})

export default client